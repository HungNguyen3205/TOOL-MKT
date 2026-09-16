<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PostComment;
use App\Jobs\PublishScheduledCommentJob;
use Illuminate\Support\Facades\Log;

class PublishDueComments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comments:publish-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish due scheduled comments to Facebook';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding due comments to publish...');

        // Find comments that are scheduled and due
        $dueComments = PostComment::where('status', PostComment::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->whereNull('facebook_comment_id')
            ->get();

        $count = $dueComments->count();
        $this->info("Found {$count} comments due for publishing.");

        foreach ($dueComments as $comment) {
            // Use atomic update to prevent duplicate dispatching
            $updated = PostComment::where('id', $comment->id)
                ->where('status', PostComment::STATUS_SCHEDULED)
                ->update(['status' => PostComment::STATUS_PUBLISHING]);

            if ($updated) {
                PublishScheduledCommentJob::dispatch($comment);
                $this->info("Dispatched comment {$comment->id} for publishing.");
            } else {
                Log::warning("Comment {$comment->id} was picked up but could not be locked for publishing (already processing?).");
            }
        }

        $this->info('Done processing due comments.');
    }
}
