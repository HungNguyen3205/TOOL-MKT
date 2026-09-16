<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ScheduledComment;
use App\Models\Publication;
use App\Models\FacebookPage;
use App\Services\FacebookGraphService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    protected $facebookService;

    public function __construct(FacebookGraphService $facebookService)
    {
        $this->facebookService = $facebookService;
    }

    public function getScheduled(Request $request)
    {
        $workspaceId = $request->header('X-Workspace-Id') ?? 1;
        $comments = ScheduledComment::with(['publication.post', 'facebookPage'])
            ->where('workspace_id', $workspaceId)
            ->orderBy('scheduled_at', 'desc')
            ->paginate(20);
            
        return response()->json($comments);
    }

    public function schedule(Request $request)
    {
        $request->validate([
            'publication_id' => 'required|exists:publications,id',
            'message' => 'required|string',
            'scheduled_at' => 'required|date',
            'image_path' => 'nullable|string'
        ]);

        $workspaceId = $request->header('X-Workspace-Id') ?? 1;
        $publication = Publication::findOrFail($request->publication_id);

        $comment = ScheduledComment::create([
            'workspace_id' => $workspaceId,
            'facebook_page_id' => $publication->facebook_page_id,
            'publication_id' => $publication->id,
            'message' => $request->message,
            'image_path' => $request->image_path,
            'scheduled_at' => Carbon::parse($request->scheduled_at),
            'status' => 'queued',
            'created_by' => auth()->id() ?? 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lên lịch bình luận thành công.',
            'data' => $comment
        ]);
    }

    public function destroyScheduled($id)
    {
        $comment = ScheduledComment::findOrFail($id);
        $comment->delete();
        return response()->json(['success' => true]);
    }

    public function getLiveComments($publicationId)
    {
        $publication = Publication::with('facebookPage')->findOrFail($publicationId);
        
        if (!$publication->external_post_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bài viết này chưa được đăng thành công lên Facebook (chưa có ID bài viết).'
            ], 422);
        }

        $page = $publication->facebookPage;
        
        try {
            // Verify token
            $this->facebookService->verifyPageToken($page->page_id, $page->access_token);
            
            // Get comments
            $comments = $this->facebookService->getPostComments($page->access_token, $publication->external_post_id);
            
            return response()->json([
                'success' => true,
                'data' => $comments['data'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy bình luận: ' . $e->getMessage()
            ], 500);
        }
    }

    public function replyToComment(Request $request)
    {
        $request->validate([
            'publication_id' => 'required|exists:publications,id',
            'target_comment_id' => 'required|string', // The ID of the comment to reply to, or the post ID to add a top-level comment
            'message' => 'required|string',
            'image_path' => 'nullable|string'
        ]);

        $publication = Publication::with('facebookPage')->findOrFail($request->publication_id);
        $page = $publication->facebookPage;

        try {
            $this->facebookService->verifyPageToken($page->page_id, $page->access_token);
            
            $result = $this->facebookService->postComment(
                $page->access_token,
                $request->target_comment_id,
                $request->message,
                $request->image_path ? storage_path('app/public/' . $request->image_path) : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã gửi bình luận.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi bình luận: ' . $e->getMessage()
            ], 500);
        }
    }
}
