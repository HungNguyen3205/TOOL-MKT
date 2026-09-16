<?php

namespace App\Jobs;

use App\Models\PostComment;
use App\Models\Post;
use App\Services\FacebookCommentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class PublishScheduledCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $comment;

    /**
     * Create a new job instance.
     */
    public function __construct(PostComment $comment)
    {
        $this->comment = $comment;
    }

    /**
     * Execute the job.
     */
    public function handle(FacebookCommentService $facebookCommentService): void
    {
        // Refresh comment to get latest status
        $this->comment->refresh();

        // Safety check
        if ($this->comment->status !== PostComment::STATUS_PUBLISHING) {
            Log::info("Comment {$this->comment->id} is not in publishing status. Current status: {$this->comment->status}");
            return;
        }

        $post = $this->comment->post;

        // If post hasn't been published to Facebook yet
        if (!$post->facebook_post_id) {
            $this->comment->update([
                'status' => PostComment::STATUS_SCHEDULED, // Revert to scheduled
                'last_error_message' => 'POST_NOT_PUBLISHED_YET: Bài viết chưa được đăng lên Facebook.',
                // Can implement exponential backoff by updating scheduled_at here if needed
            ]);
            
            // Re-dispatch after a delay (e.g., 5 minutes)
            self::dispatch($this->comment)->delay(now()->addMinutes(5));
            return;
        }

        try {
            $success = $facebookCommentService->publishComment($this->comment);
            
            if (!$success && $this->comment->attempt_count < 3) {
                // If failed, revert to scheduled for retry if under limit
                $this->comment->update([
                    'status' => PostComment::STATUS_SCHEDULED,
                    'scheduled_at' => now()->addMinutes(15) // Retry after 15 mins
                ]);
            }
        } catch (Exception $e) {
            Log::error("Failed to publish comment {$this->comment->id}: {$e->getMessage()}");
            
            if ($this->comment->attempt_count < 3) {
                $this->comment->update([
                    'status' => PostComment::STATUS_SCHEDULED,
                    'scheduled_at' => now()->addMinutes(15)
                ]);
            } else {
                $this->comment->update([
                    'status' => PostComment::STATUS_FAILED,
                    'last_error_message' => $e->getMessage()
                ]);
            }
        }
    }
}
