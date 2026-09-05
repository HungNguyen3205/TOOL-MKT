<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\FacebookPage;
use App\Services\FacebookGraphService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class PublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    
    // Retry configuration
    public $tries = 3;
    public $backoff = [60, 180]; // Retry sau 1 phut, 3 phut
    public $timeout = 120;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function handle(FacebookGraphService $fbService): void
    {
        // Kiểm tra post status.
        $post = Post::where('id', $this->post->id)
                    ->where('status', Post::STATUS_PUBLISHING)
                    ->first();

        if (!$post) {
            Log::info("PublishScheduledPostJob skipped: Post {$this->post->id} is not in publishing state.");
            return;
        }

        if ($post->facebook_post_id) {
            Log::info("PublishScheduledPostJob skipped: Post {$post->id} already has a facebook_post_id.");
            // Đảm bảo status là published
            $post->update(['status' => Post::STATUS_PUBLISHED]);
            return;
        }

        $page = FacebookPage::where('page_id', $post->facebook_page_id)->first();
        if (!$page || !$page->access_token) {
            $this->fail(new Exception("Facebook Page not found or access token missing."));
            return;
        }

        $message = $post->content;
        if (!empty($post->hashtags) && is_array($post->hashtags)) {
            $message .= "\n\n" . implode(' ', array_map(function($tag) {
                return str_starts_with($tag, '#') ? $tag : '#' . $tag;
            }, $post->hashtags));
        }

        $primaryMedia = $post->media()
            ->where('type', 'image')
            ->where('status', 'ready')
            ->first(fn ($item) => $item->pivot?->role === 'primary');

        $result = null;

        try {
            if ($primaryMedia && $primaryMedia->path) {
                $photoPath = Storage::disk($primaryMedia->disk)->path($primaryMedia->path);
                $result = $fbService->publishPhotoPost($page->page_id, $page->access_token, $message, $photoPath);
            } else {
                $result = $fbService->publishTextPost($page->page_id, $page->access_token, $message);
            }
        } catch (\Exception $e) {
            $message = strtolower($e->getMessage());
            // Lỗi quyền/token không thể retry
            if (str_contains($message, 'error validating access token') || str_contains($message, 'invalid token') || str_contains($message, 'permissions')) {
                $this->fail($e);
                return;
            }
            throw $e; // Các lỗi khác để Laravel tự retry
        }

        $facebookPostId = $result['post_id'] ?? ($result['id'] ?? null);

        if (!$facebookPostId) {
            $this->fail(new Exception("Published successfully but no Post ID returned."));
            return;
        }

        $post->update([
            'status' => Post::STATUS_PUBLISHED,
            'facebook_post_id' => $facebookPostId,
            'published_at' => now(),
            'publish_error' => null
        ]);
    }

    public function failed(\Throwable $exception)
    {
        Log::error("PublishScheduledPostJob finally failed for Post ID {$this->post->id}: " . $exception->getMessage());
        
        $post = Post::find($this->post->id);
        if ($post) {
            $post->update([
                'status' => Post::STATUS_FAILED,
                'publish_error' => $exception->getMessage()
            ]);
        }
    }
}
