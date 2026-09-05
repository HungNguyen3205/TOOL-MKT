<?php

namespace App\Jobs;

use App\Models\Publication;
use App\Models\PublicationAttempt;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Services\FacebookPublishService;
use App\Services\FacebookPostFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PublishFacebookTextPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $publicationId;
    
    // Limits the total attempts
    public int $tries = 3;

    /**
     * Exponential backoff
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 min, 5 min, 15 min
    }

    public function __construct(int $publicationId)
    {
        $this->publicationId = $publicationId;
        $this->onQueue(config('services.facebook.queue', 'facebook-publish'));
    }

    public function handle(FacebookPublishService $publishService, FacebookPostFormatter $formatter)
    {
        $publication = Publication::with('facebookPage', 'post')->find($this->publicationId);

        if (!$publication) {
            return; // Publication was deleted
        }

        // If it is already published, we shouldn't run again (defend against rogue workers)
        if ($publication->status === 'published') {
            return;
        }

        $page = $publication->facebookPage;
        
        if (!$page || !$page->is_active || $page->connection_status !== 'connected') {
            $this->failPublication($publication, 'FACEBOOK_PAGE_DISCONNECTED', 'Trang không khả dụng hoặc đã ngắt kết nối.');
            return;
        }

        // Ensure we are working with the snapshot data, not the live post if it changed.
        // But formatting from a snapshot array directly might be easier, or we hydrate a dummy Post.
        $snapshot = $publication->content_snapshot;
        if (!is_array($snapshot)) {
            $this->failPublication($publication, 'INVALID_SNAPSHOT', 'Không tìm thấy bản lưu nội dung hợp lệ.');
            return;
        }
        
        $dummyPost = new Post($snapshot);
        $message = $formatter->format($dummyPost);

        if (empty($message)) {
            $this->failPublication($publication, 'EMPTY_CONTENT', 'Nội dung bài viết trống.');
            return;
        }

        // Mark as processing
        $publication->update([
            'status' => 'processing',
            'processing_at' => Carbon::now(),
            'attempts_count' => $publication->attempts_count + 1
        ]);

        $attemptNumber = $publication->attempts_count;

        // Create attempt log
        $attempt = PublicationAttempt::create([
            'publication_id' => $publication->id,
            'attempt_number' => $attemptNumber,
            'status' => 'processing',
            'started_at' => Carbon::now(),
        ]);

        try {
            // Decrypt token handled by model cast
            $token = $page->access_token;
            
            $result = $publishService->publishText(
                $page->page_id,
                $token,
                $message,
                $publication->idempotency_key ?? (string)$publication->id
            );

            // Success
            $attempt->update([
                'status' => 'success',
                'http_status' => 200,
                'completed_at' => Carbon::now(),
                'response_metadata' => $result['response_data'] ?? null
            ]);

            $publication->update([
                'status' => 'published',
                'published_at' => Carbon::now(),
                'external_post_id' => $result['external_post_id'],
                'last_error_code' => null,
                'last_error_message' => null
            ]);

            // Update original post status if needed
            if ($publication->post) {
                $publication->post->update([
                    'status' => 'published',
                    'published_at' => Carbon::now(),
                    'last_publication_status' => 'success',
                    'last_facebook_post_id' => $result['external_post_id'],
                ]);
            }

        } catch (Exception $e) {
            $errorCode = $publishService->mapErrorCode($e);
            
            $attempt->update([
                'status' => 'failed',
                'platform_error_code' => $e->getCode(),
                'error_category' => $errorCode,
                'error_message' => $e->getMessage(),
                'completed_at' => Carbon::now(),
            ]);

            if ($this->attempts() >= $this->tries || !$publishService->isRetryableError($errorCode)) {
                // Final failure or unretryable error
                $this->failPublication($publication, $errorCode, $e->getMessage());
                // Throwing will trigger the queue's failure flow but we don't necessarily need it to retry
                // Actually, if it's not retryable, we should just let it fail gracefully so it doesn't retry
                // or use $this->fail() to tell Laravel it's a hard fail.
                $this->fail($e);
            } else {
                // Temporary failure, set back to queued to wait for next attempt
                $publication->update([
                    'status' => 'queued',
                    'last_error_code' => $errorCode,
                    'last_error_message' => $e->getMessage(),
                ]);
                
                // Rethrow to trigger Laravel's retry backoff
                throw $e;
            }
        }
    }

    protected function failPublication(Publication $publication, string $code, string $message)
    {
        $publication->update([
            'status' => 'failed',
            'failed_at' => Carbon::now(),
            'last_error_code' => $code,
            'last_error_message' => $message,
        ]);

        if ($publication->post) {
            $publication->post->update([
                'status' => 'failed',
                'last_publication_status' => 'failed',
            ]);
        }
    }
}
