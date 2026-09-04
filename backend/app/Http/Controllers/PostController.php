<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Services\PostWorkflowService;
use App\Services\ContentQualityValidator;
use App\DTOs\ContentGenerationData;
use App\Models\PostVersion;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with('brand');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('source') && $request->source !== 'all') {
            $query->where('source', $request->source);
        }

        $sort = $request->input('sort', 'updated_desc');
        switch ($sort) {
            case 'created_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'created_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'updated_asc':
                $query->orderBy('updated_at', 'asc');
                break;
            case 'updated_desc':
            default:
                $query->orderBy('updated_at', 'desc');
                break;
        }

        $posts = $query->paginate(10);
        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request, PostWorkflowService $workflow)
    {
        try {
            $data = $request->validated();
            $data['last_saved_at'] = Carbon::now();
            $post = Post::create($data);

            // Log creation
            $workflow->logActivity($post, 'created', null, $post->status);
            $workflow->createVersion($post, 'manual_edit', 'Initial creation');

            return response()->json([
                'success' => true,
                'message' => 'Lưu bài viết thành công.',
                'data' => new PostResource($post)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lưu bài viết.',
                'error_code' => 'POST_SAVE_FAILED'
            ], 500);
        }
    }

    public function show($id)
    {
        $post = Post::with('brand')->find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
                'error_code' => 'POST_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PostResource($post)
        ]);
    }

    public function update(UpdatePostRequest $request, $id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
                'error_code' => 'POST_NOT_FOUND'
            ], 404);
        }

        if (in_array($post->status, ['in_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chỉnh sửa bài viết đang chờ duyệt.',
                'error_code' => 'POST_LOCKED_FOR_REVIEW'
            ], 422);
        }

        try {
            $data = $request->validated();
            $post = $workflow->handleManualEdit($post, $data);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật bài viết thành công.',
                'data' => new PostResource($post)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật bài viết.',
                'error_code' => 'POST_UPDATE_FAILED'
            ], 500);
        }
    }

    public function destroy($id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
                'error_code' => 'POST_NOT_FOUND'
            ], 404);
        }

        try {
            $oldStatus = $post->status;
            $post->delete();
            $workflow->logActivity($post, 'soft_deleted', $oldStatus, null);

            return response()->json([
                'success' => true,
                'message' => 'Xóa bài viết thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa bài viết.',
                'error_code' => 'POST_DELETE_FAILED'
            ], 500);
        }
    }

    public function duplicate($id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
                'error_code' => 'POST_NOT_FOUND'
            ], 404);
        }

        try {
            $newPost = $post->replicate([
                'status', 'last_saved_at', 'created_at', 'updated_at', 'deleted_at',
                'published_at', 'last_publication_status', 'last_facebook_post_id',
                'quality_score', 'quality_status', 'quality_result', 'quality_checked_at',
                'submitted_for_review_at', 'approved_at', 'ready_at', 'content_version',
                'last_content_hash', 'review_note', 'last_edited_by', 'approved_by'
            ]);
            $newPost->title = $post->title . ' - Bản sao';
            $newPost->status = 'draft';
            $newPost->content_version = 0;
            $newPost->save();

            $workflow->logActivity($newPost, 'duplicated', null, 'draft');
            $workflow->createVersion($newPost, 'manual_edit', 'Duplicated from post ' . $post->id);

            return response()->json([
                'success' => true,
                'message' => 'Nhân bản bài viết thành công.',
                'data' => new PostResource($newPost)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể nhân bản bài viết.',
                'error_code' => 'POST_DUPLICATE_FAILED'
            ], 500);
        }
    }

    // Removed old updateStatus method because we now use workflow methods

    public function qualityCheck($id, ContentQualityValidator $validator)
    {
        $post = Post::with(['brand', 'contentTemplate'])->find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        try {
            $dtoData = [
                'topic' => $post->title,
                'main_information' => $post->content,
                'objective' => $post->objective ?? 'sales',
                'length' => $post->content_length ?? 'medium',
                'number_of_versions' => 1,
            ];
            $dto = ContentGenerationData::fromArray($dtoData, $post->brand, $post->contentTemplate);
            
            $versionData = [
                'title' => $post->title,
                'content' => $post->content,
                'cta' => $post->cta,
                'hashtags' => $post->hashtags ?? [],
            ];

            $results = $validator->validateVersions([$versionData], $dto);
            $qualityResult = $results[0]['quality'];

            $post->quality_score = $qualityResult['score'];
            $post->quality_status = $qualityResult['status'];
            $post->quality_result = $qualityResult;
            $post->quality_checked_at = Carbon::now();
            $post->save();

            return response()->json([
                'success' => true,
                'message' => 'Đã kiểm tra chất lượng.',
                'data' => $qualityResult
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submitReview($id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        try {
            $post = $workflow->submitForReview($post);
            return response()->json(['success' => true, 'message' => 'Đã gửi duyệt.', 'data' => new PostResource($post)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'INVALID_STATUS_TRANSITION'], 422);
        }
    }

    public function approve($id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        try {
            $post = $workflow->approve($post);
            return response()->json(['success' => true, 'message' => 'Đã duyệt bài.', 'data' => new PostResource($post)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'INVALID_STATUS_TRANSITION'], 422);
        }
    }

    public function requestChanges(Request $request, $id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        try {
            $post = $workflow->requestChanges($post, $request->input('note', ''));
            return response()->json(['success' => true, 'message' => 'Đã yêu cầu chỉnh sửa.', 'data' => new PostResource($post)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'REVIEW_NOTE_REQUIRED'], 422);
        }
    }

    public function markReady($id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        try {
            $post = $workflow->markReady($post);
            return response()->json(['success' => true, 'message' => 'Đã đánh dấu sẵn sàng.', 'data' => new PostResource($post)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'CONTENT_CHANGED_AFTER_APPROVAL'], 422);
        }
    }

    public function returnToDraft($id, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        try {
            $post = $workflow->returnToDraft($post);
            return response()->json(['success' => true, 'message' => 'Đã trả về bản nháp.', 'data' => new PostResource($post)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'INVALID_STATUS_TRANSITION'], 422);
        }
    }

    public function versions($id)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        $versions = $post->versions()->orderBy('version_number', 'desc')->get();
        return response()->json(['success' => true, 'data' => $versions]);
    }

    public function restoreVersion($id, $versionId, PostWorkflowService $workflow)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        $version = PostVersion::where('post_id', $id)->find($versionId);
        if (!$version) return response()->json(['success' => false, 'error_code' => 'POST_VERSION_NOT_FOUND'], 404);

        try {
            $post = $workflow->restoreVersion($post, $version);
            return response()->json(['success' => true, 'message' => 'Đã khôi phục phiên bản.', 'data' => new PostResource($post)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'POST_VERSION_RESTORE_FAILED'], 422);
        }
    }

    public function activities($id)
    {
        $post = Post::find($id);
        if (!$post) return response()->json(['success' => false, 'error_code' => 'POST_NOT_FOUND'], 404);

        $activities = $post->activityLogs()->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $activities]);
    }
}
