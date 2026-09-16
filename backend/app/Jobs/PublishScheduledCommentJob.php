<?php

namespace App\Jobs;

use App\Models\PostComment;
use App\Services\FacebookCommentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishScheduledCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $commentId)
    {
        $this->onQueue(config('services.facebook.queue', 'facebook-publish'));
    }

    public function handle(FacebookCommentService $facebookCommentService): void
    {
        $comment = PostComment::with(['post', 'facebookPage'])->find($this->commentId);

        if (!$comment || $comment->status === PostComment::STATUS_PUBLISHED || $comment->facebook_comment_id) {
            return;
        }

        if ($comment->status !== PostComment::STATUS_PUBLISHING) {
            Log::info('Comment job skipped because it was not atomically claimed.', [
                'comment_id' => $this->commentId,
                'status' => $comment->status,
            ]);
            return;
        }

        $post = $comment->post;
        $facebookPostId = $post?->facebook_post_id ?: $post?->last_facebook_post_id;

        if (!$facebookPostId) {
            // Let the minute scheduler pick it up again. Do not dispatch a job whose
            // precondition immediately becomes false.
            $comment->update([
                'status' => PostComment::STATUS_SCHEDULED,
                'scheduled_at' => now()->addMinutes(5),
                'last_error_code' => 'POST_NOT_PUBLISHED_YET',
                'last_error_message' => 'Bài viết chưa có Facebook Post ID; sẽ thử lại sau 5 phút.',
            ]);
            return;
        }

        $published = $facebookCommentService->publishComment($comment);

        if ($published) {
            return;
        }

        $comment->refresh();

        if ($comment->attempt_count < 3) {
            $delayMinutes = match ($comment->attempt_count) {
                1 => 5,
                2 => 15,
                default => 30,
            };

            $comment->update([
                'status' => PostComment::STATUS_SCHEDULED,
                'scheduled_at' => now()->addMinutes($delayMinutes),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        PostComment::query()
            ->whereKey($this->commentId)
            ->whereNull('facebook_comment_id')
            ->update([
                'status' => PostComment::STATUS_FAILED,
                'last_error_message' => $exception->getMessage(),
            ]);

        Log::error('PublishScheduledCommentJob failed.', [
            'comment_id' => $this->commentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
