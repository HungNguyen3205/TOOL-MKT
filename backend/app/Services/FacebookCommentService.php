<?php

namespace App\Services;

use App\Models\FacebookPage;
use App\Models\PostComment;
use App\Models\CommentPublicationAttempt;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookCommentService
{
    /**
     * Publish a comment to a specific Facebook post.
     * 
     * @param PostComment $comment
     * @return bool
     * @throws Exception
     */
    public function publishComment(PostComment $comment): bool
    {
        $post = $comment->post;
        $page = $comment->facebookPage ?? $post->facebookPage;
        
        if (!$page) {
            throw new Exception('Bình luận không có Facebook Page.');
        }

        if (!$page->is_active || $page->connection_status !== 'connected') {
            throw new Exception('Trang Facebook chưa được kết nối hoặc đã bị vô hiệu hóa.');
        }

        $fbPostId = $post->facebook_post_id ?? $post->last_facebook_post_id;
        
        if (!$fbPostId) {
            throw new Exception('Bài viết chưa được đăng lên Facebook nên chưa thể đăng bình luận.');
        }

        // Validate permissions if needed (pages_manage_engagement)
        $scopes = $page->granted_scopes ?? [];
        if (is_array($scopes) && !in_array('pages_manage_engagement', $scopes) && !in_array('pages_read_engagement', $scopes)) {
            // Note: Some apps might just rely on publish_pages or others, so we might just attempt and catch the error.
        }

        $attempt = $comment->attempts()->create([
            'attempt_number' => $comment->attempt_count + 1,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $comment->increment('attempt_count');
        $comment->update(['last_attempt_at' => now()]);

        try {
            $response = Http::withToken($page->access_token)
                ->post("https://graph.facebook.com/v19.0/{$fbPostId}/comments", [
                    'message' => $comment->content,
                ]);

            $attempt->update([
                'http_status' => $response->status(),
                'finished_at' => now(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $attempt->update([
                    'status' => 'success',
                    'response_meta' => $data,
                ]);

                $comment->update([
                    'facebook_comment_id' => $data['id'],
                    'status' => PostComment::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);

                return true;
            }

            // Handle Error
            $error = $response->json('error');
            $errorCode = $error['code'] ?? null;
            $errorSubcode = $error['error_subcode'] ?? null;
            $errorMessage = $error['message'] ?? 'Unknown error';

            $attempt->update([
                'status' => 'failed',
                'facebook_error_code' => $errorCode,
                'facebook_error_subcode' => $errorSubcode,
                'message' => $errorMessage,
                'response_meta' => $error,
            ]);

            $comment->update([
                'status' => PostComment::STATUS_FAILED,
                'last_error_code' => $errorCode,
                'last_error_message' => $errorMessage,
            ]);

            return false;

        } catch (Exception $e) {
            $attempt->update([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $comment->update([
                'status' => PostComment::STATUS_FAILED,
                'last_error_message' => $e->getMessage(),
            ]);

            Log::error('FacebookCommentService Publish Error: ' . $e->getMessage(), [
                'comment_id' => $comment->id,
            ]);

            return false;
        }
    }
}
