<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Jobs\GeneratePostContentJob;
use Illuminate\Support\Facades\Validator;

class PostContentController extends Controller
{
    public function generate(Request $request, $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bài viết.',
                'error_code' => 'POST_NOT_FOUND'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'error_code' => 'VALIDATION_FAILED',
                'errors' => $validator->errors()
            ], 422);
        }

        // If the post has a different status, allow generation if it's draft or changes_requested or if it's new
        if (!in_array($post->status, ['draft', 'changes_requested', 'failed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể sinh nội dung khi bài viết đang là bản nháp.',
                'error_code' => 'INVALID_STATUS'
            ], 422);
        }

        // Save topic if provided in source_input
        if ($request->has('topic')) {
            $sourceInput = $post->source_input ?? [];
            $sourceInput['topic'] = $request->topic;
            $post->source_input = $sourceInput;
            $post->title = $request->topic; // Temporary title
        }

        $post->status = Post::STATUS_GENERATING_CONTENT;
        $post->generation_error = null;
        $post->save();

        GeneratePostContentJob::dispatch($post);

        return response()->json([
            'success' => true,
            'message' => 'Đang sinh nội dung...',
            'data' => $post->fresh()
        ]);
    }
}
