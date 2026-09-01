<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
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

    public function store(StorePostRequest $request)
    {
        try {
            $data = $request->validated();
            $data['last_saved_at'] = Carbon::now();
            $post = Post::create($data);

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

    public function update(UpdatePostRequest $request, $id)
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
            $data = $request->validated();
            $data['last_saved_at'] = Carbon::now();
            $post->update($data);

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

    public function destroy($id)
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
            $post->delete();
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

    public function duplicate($id)
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
            $newPost = $post->replicate(['status', 'last_saved_at', 'created_at', 'updated_at', 'deleted_at']);
            $newPost->title = $post->title . ' - Bản sao';
            $newPost->status = 'draft';
            $newPost->save();

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

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:draft,ready'
        ]);

        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
                'error_code' => 'POST_NOT_FOUND'
            ], 404);
        }

        if ($request->status === 'ready') {
            if (empty($post->title) || empty($post->content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bài viết chưa đủ điều kiện để sẵn sàng.',
                    'error_code' => 'INVALID_STATUS_TRANSITION'
                ], 422);
            }
        }

        try {
            $post->status = $request->status;
            $post->save();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công.',
                'data' => new PostResource($post)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái.',
                'error_code' => 'POST_UPDATE_FAILED'
            ], 500);
        }
    }
}
