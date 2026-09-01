<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\FacebookPage;
use App\Models\PublicationLog;
use App\Services\FacebookGraphService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FacebookPublishController extends Controller
{
    protected $facebookService;

    public function __construct(FacebookGraphService $facebookService)
    {
        $this->facebookService = $facebookService;
    }

    public function publish(Request $request, $postId)
    {
        $request->validate([
            'facebook_page_id' => 'required|integer',
            'confirmation' => 'required|boolean|accepted',
        ]);

        $post = Post::findOrFail($postId);
        $page = FacebookPage::findOrFail($request->facebook_page_id);

        if ($post->status !== 'ready') {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết chưa sẵn sàng để đăng.',
                'error_code' => 'POST_NOT_READY'
            ], 422);
        }

        if (!$page->is_active || $page->connection_status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Facebook Page không khả dụng hoặc đã mất kết nối.',
                'error_code' => 'FACEBOOK_PAGE_NOT_AVAILABLE'
            ], 422);
        }

        // Prevent duplicate / concurrent posting using a simple database lock or checking processing status
        $isProcessing = PublicationLog::where('post_id', $post->id)
            ->where('facebook_page_id', $page->id)
            ->where('status', 'processing')
            ->where('attempted_at', '>=', Carbon::now()->subMinutes(5))
            ->exists();

        if ($isProcessing) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết đang được xử lý đăng lên trang này.',
                'error_code' => 'PUBLICATION_IN_PROGRESS'
            ], 422);
        }

        // Check if already published
        $isPublished = PublicationLog::where('post_id', $post->id)
            ->where('facebook_page_id', $page->id)
            ->where('status', 'success')
            ->exists();

        if ($isPublished) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết đã được đăng thành công lên trang này trước đó.',
                'error_code' => 'POST_ALREADY_PUBLISHED'
            ], 422);
        }

        // Format message
        $message = $this->formatPostContent($post);

        // Create initial processing log
        $log = PublicationLog::create([
            'post_id' => $post->id,
            'facebook_page_id' => $page->id,
            'action' => 'publish_now',
            'status' => 'processing',
            'request_type' => 'text',
            'attempted_at' => Carbon::now(),
        ]);

        try {
            // Publish to Facebook
            $response = $this->facebookService->publishTextPost($page->page_id, $page->access_token, $message);
            
            $fbPostId = $response['id'] ?? null;

            // Update log to success
            $log->update([
                'status' => 'success',
                'facebook_post_id' => $fbPostId,
                'published_at' => Carbon::now(),
                'http_status' => 200,
            ]);

            // Update Post
            $post->update([
                'published_at' => Carbon::now(),
                'last_publication_status' => 'success',
                'last_facebook_post_id' => $fbPostId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đăng bài lên Facebook thành công.',
                'data' => [
                    'post_id' => $post->id,
                    'facebook_page' => [
                        'id' => $page->id,
                        'page_id' => $page->page_id,
                        'page_name' => $page->page_name
                    ],
                    'publication' => $log
                ]
            ]);

        } catch (\Exception $e) {
            // Update log to failed
            $log->update([
                'status' => 'failed',
                'error_code' => 'FACEBOOK_PUBLISH_FAILED',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể đăng bài. Vui lòng xem chi tiết lỗi.',
                'error_code' => 'FACEBOOK_PUBLISH_FAILED',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }

    public function history($postId)
    {
        $logs = PublicationLog::with('facebookPage')
            ->where('post_id', $postId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    protected function formatPostContent(Post $post): string
    {
        $parts = [];
        
        if (!empty($post->title)) {
            $parts[] = $post->title;
        }
        
        if (!empty($post->content)) {
            $parts[] = $post->content;
        }
        
        if (!empty($post->cta)) {
            $parts[] = $post->cta;
        }

        if (!empty($post->hashtags) && is_array($post->hashtags)) {
            $hashtags = array_map(function($tag) {
                return str_starts_with($tag, '#') ? $tag : '#' . $tag;
            }, $post->hashtags);
            $parts[] = implode(' ', $hashtags);
        }

        return implode("\n\n", $parts);
    }
}
