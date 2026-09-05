<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\FacebookPage;
use App\Models\Publication;
use App\Jobs\PublishFacebookTextPost;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\FacebookGraphService;

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
            'idempotency_key' => 'nullable|string|max:255'
        ]);

        $post = Post::findOrFail($postId);
        $page = FacebookPage::findOrFail($request->facebook_page_id);

        if ($post->status !== 'ready') {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết chưa sẵn sàng để đăng. Trạng thái bài viết phải là Sẵn sàng đăng.',
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

        // Kiểm tra token Page bằng endpoint phù hợp trước khi cho đăng.
        try {
            $this->facebookService->verifyPageToken($page->page_id, $page->access_token);
        } catch (\Exception $e) {
            $page->update([
                'connection_status' => 'token_expired',
                'last_error_code' => 'FACEBOOK_TOKEN_INVALID',
                'last_error_message' => $e->getMessage(),
                'last_verified_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token đã hết hạn hoặc không hợp lệ. Vui lòng kết nối lại.',
                'error_code' => 'FACEBOOK_TOKEN_INVALID'
            ], 422);
        }

        // Generate content hash for snapshot comparison
        $contentHash = md5($post->title . $post->content . $post->cta . implode(',', $post->hashtags ?? []));
        
        // Generate Idempotency Key if not provided
        // Use a composite key based on post_id, version, page, and content hash
        $idempotencyKey = $request->idempotency_key ?: "post_{$post->id}_v{$post->content_version}_page_{$page->id}_{$contentHash}";

        // Prevent duplicate using database transaction and checking existing publication
        $result = DB::transaction(function () use ($post, $page, $idempotencyKey, $contentHash) {
            
            // Check if publication already exists for this idempotency key
            $existing = Publication::where('idempotency_key', $idempotencyKey)->first();
            
            if ($existing) {
                return $existing;
            }

            // Check if there is already a successful publication for this post/page
            $alreadyPublished = Publication::where('post_id', $post->id)
                ->where('facebook_page_id', $page->id)
                ->where('status', 'published')
                ->exists();
                
            if ($alreadyPublished) {
                throw new \Exception('POST_ALREADY_PUBLISHED');
            }

            // Create a snapshot
            $snapshot = [
                'post_id' => $post->id,
                'post_version' => $post->content_version,
                'title' => $post->title,
                'content' => $post->content,
                'cta' => $post->cta,
                'hashtags' => $post->hashtags,
                'brand_id' => $post->brand_id,
                'quality_score' => $post->quality_score,
                'approved_at' => $post->approved_at
            ];

            // Create new publication in queued status
            $publication = Publication::create([
                'post_id' => $post->id,
                'facebook_page_id' => $page->id,
                'platform' => 'facebook',
                'publication_type' => 'text',
                'status' => 'queued',
                'content_snapshot' => $snapshot,
                'content_hash' => $contentHash,
                'idempotency_key' => $idempotencyKey,
                'queued_at' => Carbon::now(),
                // 'requested_by' => auth()->id() // Uncomment if auth is active
            ]);

            return $publication;
        });

        // If it was already published in a separate action
        if (is_string($result) && $result === 'POST_ALREADY_PUBLISHED') {
             return response()->json([
                'success' => false,
                'message' => 'Bài viết đã được đăng thành công lên trang này trước đó.',
                'error_code' => 'POST_ALREADY_PUBLISHED'
            ], 422);
        }

        // If it was already queued/processing by another request, just return status
        if ($result->wasRecentlyCreated) {
            // Dispatch to Queue immediately
            PublishFacebookTextPost::dispatch($result->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Bài viết đã được đưa vào hàng đợi đăng Facebook.',
                'data' => [
                    'publication_id' => $result->id,
                    'status' => 'queued'
                ]
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu đăng bài đã được nhận trước đó.',
                'data' => [
                    'publication_id' => $result->id,
                    'status' => $result->status
                ]
            ]);
        }
    }
    
    public function retry(Request $request, $publicationId)
    {
        $publication = Publication::with('facebookPage')->findOrFail($publicationId);
        
        if ($publication->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể thử lại các bài đăng bị lỗi.',
                'error_code' => 'PUBLICATION_RETRY_NOT_ALLOWED'
            ], 422);
        }
        
        $page = $publication->facebookPage;
        
        if (!$page || !$page->is_active || $page->connection_status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Kết nối Facebook đã hết hạn. Vui lòng kết nối lại Page trước khi thử lại.',
                'error_code' => 'FACEBOOK_PAGE_DISCONNECTED'
            ], 422);
        }
        
        $publication->update([
            'status' => 'queued',
            'queued_at' => Carbon::now(),
        ]);
        
        PublishFacebookTextPost::dispatch($publication->id);
        
        return response()->json([
            'success' => true,
            'message' => 'Đã đưa vào hàng đợi để thử lại.',
            'data' => [
                'publication_id' => $publication->id,
                'status' => 'queued'
            ]
        ]);
    }

    public function history($postId)
    {
        $logs = Publication::with(['facebookPage', 'attempts' => function($query) {
                $query->orderBy('attempt_number', 'desc');
            }])
            ->where('post_id', $postId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    public function allHistory()
    {
        $logs = Publication::with(['facebookPage', 'post', 'attempts' => function($query) {
                $query->orderBy('attempt_number', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
