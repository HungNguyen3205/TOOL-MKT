<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ScheduledComment;
use App\Services\FacebookGraphService;
use Illuminate\Support\Facades\Log;

class PublishFacebookComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $commentId;

    public function __construct($commentId)
    {
        $this->commentId = $commentId;
        $this->onQueue('facebook-publish');
    }

    public function handle(FacebookGraphService $facebookService): void
    {
        $comment = ScheduledComment::with(['publication.facebookPage'])->find($this->commentId);
        if (!$comment || $comment->status !== 'queued') {
            return;
        }

        $publication = $comment->publication;
        if (!$publication || !$publication->external_post_id) {
            $comment->update([
                'status' => 'failed',
                'last_error' => 'Bài viết gốc chưa được đăng lên Facebook.'
            ]);
            return;
        }

        $page = $publication->facebookPage;
        if (!$page || $page->connection_status !== 'connected') {
            $comment->update([
                'status' => 'failed',
                'last_error' => 'Facebook Page không khả dụng.'
            ]);
            return;
        }

        $comment->update(['status' => 'processing']);

        try {
            $facebookService->verifyPageToken($page->page_id, $page->access_token);
            
            $result = $facebookService->postComment(
                $page->access_token,
                $publication->external_post_id,
                $comment->message,
                $comment->image_path ? storage_path('app/public/' . $comment->image_path) : null
            );

            $comment->update([
                'status' => 'published',
                'external_comment_id' => $result['id'] ?? null,
                'last_error' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi đăng ScheduledComment: ' . $e->getMessage());
            $comment->update([
                'status' => 'failed',
                'last_error' => $e->getMessage()
            ]);
        }
    }
}
