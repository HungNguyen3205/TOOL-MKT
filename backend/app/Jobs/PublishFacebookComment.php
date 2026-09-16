<?php

namespace App\Jobs;

use App\Models\ScheduledComment;
use App\Services\FacebookGraphService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PublishFacebookComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    protected int $commentId;

    public function __construct(int $commentId)
    {
        $this->commentId = $commentId;
        $this->onQueue(config('services.facebook.queue', 'facebook-publish'));
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(FacebookGraphService $facebookService): void
    {
        $comment = ScheduledComment::with(['publication.facebookPage'])->find($this->commentId);

        if (!$comment || $comment->status === 'published' || $comment->external_comment_id) {
            return;
        }

        if ($comment->status !== 'processing') {
            Log::info('Scheduled comment job skipped because it was not claimed.', [
                'comment_id' => $this->commentId,
                'status' => $comment->status,
            ]);
            return;
        }

        $publication = $comment->publication;
        if (!$publication || !$publication->external_post_id) {
            throw new RuntimeException('Bài viết gốc chưa có Facebook Post ID.');
        }

        $page = $publication->facebookPage;
        if (!$page || !$page->is_active || $page->connection_status !== 'connected' || !$page->access_token) {
            throw new RuntimeException('Facebook Page không khả dụng hoặc thiếu access token.');
        }

        $facebookService->verifyPageToken($page->page_id, $page->access_token);

        $result = $facebookService->postComment(
            $page->access_token,
            $publication->external_post_id,
            $comment->message,
            $comment->image_path ? storage_path('app/public/' . $comment->image_path) : null
        );

        $externalCommentId = $result['id'] ?? null;
        if (!$externalCommentId) {
            throw new RuntimeException('Facebook không trả về ID bình luận.');
        }

        $comment->update([
            'status' => 'published',
            'external_comment_id' => $externalCommentId,
            'last_error' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        ScheduledComment::query()
            ->whereKey($this->commentId)
            ->whereNull('external_comment_id')
            ->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

        Log::error('Scheduled Facebook comment failed permanently.', [
            'comment_id' => $this->commentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
