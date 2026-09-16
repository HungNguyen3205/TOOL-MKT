<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\FacebookCommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class PostCommentController extends Controller
{
    protected $facebookCommentService;

    public function __construct(FacebookCommentService $facebookCommentService)
    {
        $this->facebookCommentService = $facebookCommentService;
    }

    public function index(Post $post)
    {
        $comments = $post->comments()->with('attempts')->latest()->get();
        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    public function store(Request $request, Post $post)
    {
        $request->validate([
            'content' => 'required|string|max:8000', // Meta limit
            'scheduled_at' => 'nullable|date|after:now',
            'schedule_type' => 'nullable|in:absolute,after_post',
            'delay_minutes' => 'nullable|integer|min:0',
        ]);

        if (trim($request->content) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung không được để trống.',
            ], 422);
        }

        $status = PostComment::STATUS_DRAFT;
        if ($request->scheduled_at || $request->schedule_type === 'after_post') {
            $status = PostComment::STATUS_SCHEDULED;
        }

        $comment = $post->comments()->create([
            'content' => $request->content,
            'status' => $status,
            'scheduled_at' => $request->scheduled_at,
            'schedule_type' => $request->schedule_type ?? 'absolute',
            'delay_minutes' => $request->delay_minutes,
            'facebook_page_id' => $post->facebook_page_id,
            'created_by' => auth()->id() ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo bình luận.',
            'data' => $comment
        ]);
    }

    public function show(PostComment $comment)
    {
        return response()->json([
            'success' => true,
            'data' => $comment->load('attempts')
        ]);
    }

    public function update(Request $request, PostComment $comment)
    {
        if (in_array($comment->status, [PostComment::STATUS_PUBLISHING, PostComment::STATUS_PUBLISHED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể sửa bình luận đang hoặc đã đăng.',
            ], 422);
        }

        $request->validate([
            'content' => 'sometimes|required|string|max:8000',
            'scheduled_at' => 'nullable|date|after:now',
            'schedule_type' => 'nullable|in:absolute,after_post',
            'delay_minutes' => 'nullable|integer|min:0',
        ]);

        $comment->update($request->only(['content', 'scheduled_at', 'schedule_type', 'delay_minutes']));

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật bình luận.',
            'data' => $comment
        ]);
    }

    public function destroy(PostComment $comment)
    {
        if (in_array($comment->status, [PostComment::STATUS_PUBLISHING, PostComment::STATUS_PUBLISHED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa bình luận đang hoặc đã đăng.',
            ], 422);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bình luận.',
        ]);
    }

    public function publishNow(PostComment $comment)
    {
        if ($comment->status === PostComment::STATUS_PUBLISHING) {
            return response()->json([
                'success' => false,
                'message' => 'Bình luận đang được đăng.',
            ], 422);
        }

        if ($comment->status === PostComment::STATUS_PUBLISHED) {
            return response()->json([
                'success' => false,
                'message' => 'Bình luận đã được đăng.',
            ], 422);
        }

        $comment->update([
            'status' => PostComment::STATUS_PUBLISHING
        ]);

        try {
            $success = $this->facebookCommentService->publishComment($comment);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Đã đăng bình luận thành công.',
                    'data' => $comment->refresh()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Đăng bình luận thất bại: ' . $comment->last_error_message,
                    'data' => $comment->refresh()
                ], 422);
            }
        } catch (Exception $e) {
            $comment->update([
                'status' => PostComment::STATUS_FAILED,
                'last_error_message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function schedule(Request $request, PostComment $comment)
    {
        $request->validate([
            'scheduled_at' => 'required_if:schedule_type,absolute|date|after:now',
            'schedule_type' => 'nullable|in:absolute,after_post',
            'delay_minutes' => 'nullable|integer|min:0',
        ]);

        if (in_array($comment->status, [PostComment::STATUS_PUBLISHED, PostComment::STATUS_PUBLISHING])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lên lịch cho bình luận này.',
            ], 422);
        }

        $comment->update([
            'status' => PostComment::STATUS_SCHEDULED,
            'schedule_type' => $request->schedule_type ?? 'absolute',
            'scheduled_at' => $request->scheduled_at,
            'delay_minutes' => $request->delay_minutes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lên lịch bình luận.',
            'data' => $comment
        ]);
    }

    public function cancel(PostComment $comment)
    {
        if ($comment->status !== PostComment::STATUS_SCHEDULED) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy bình luận đang được lên lịch.',
            ], 422);
        }

        $comment->update([
            'status' => PostComment::STATUS_CANCELLED,
            'scheduled_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy lịch bình luận.',
            'data' => $comment
        ]);
    }
}
