<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledCommentJob;
use App\Models\PostComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishDueComments extends Command
{
    protected $signature = 'comments:publish-due {--limit=100}';
    protected $description = 'Atomically dispatch due scheduled comments to Facebook';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $dispatched = 0;

        // Resolve relative schedules without coupling comment logic to the post publisher.
        PostComment::query()
            ->with('post')
            ->where('status', PostComment::STATUS_SCHEDULED)
            ->where('schedule_type', 'after_post')
            ->whereNull('scheduled_at')
            ->whereHas('post', function ($query) {
                $query->whereNotNull('published_at')
                    ->where(function ($postQuery) {
                        $postQuery->whereNotNull('facebook_post_id')
                            ->orWhereNotNull('last_facebook_post_id');
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (PostComment $comment) {
                $comment->update([
                    'scheduled_at' => $comment->post->published_at
                        ->copy()
                        ->addMinutes($comment->delay_minutes ?? 5),
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);
            });

        PostComment::query()
            ->where('status', PostComment::STATUS_SCHEDULED)
            ->whereNull('facebook_comment_id')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $commentId) use (&$dispatched) {
                $claimed = PostComment::query()
                    ->whereKey($commentId)
                    ->where('status', PostComment::STATUS_SCHEDULED)
                    ->whereNull('facebook_comment_id')
                    ->update(['status' => PostComment::STATUS_PUBLISHING]);

                if ($claimed !== 1) {
                    return;
                }

                try {
                    PublishScheduledCommentJob::dispatch($commentId);
                    $dispatched++;
                } catch (\Throwable $exception) {
                    PostComment::query()->whereKey($commentId)->update([
                        'status' => PostComment::STATUS_SCHEDULED,
                        'last_error_message' => $exception->getMessage(),
                    ]);

                    Log::error('Could not dispatch scheduled post comment.', [
                        'comment_id' => $commentId,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Dispatched {$dispatched} due comments.");

        return self::SUCCESS;
    }
}
