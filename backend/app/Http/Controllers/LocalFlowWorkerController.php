<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Services\ImageOverlayService;
use App\Models\MediaAsset;

class LocalFlowWorkerController extends Controller
{
    /**
     * Get tasks (posts that need image generation).
     */
    public function getTasks()
    {
        $posts = Post::where('status', 'generating_image')
                     ->whereNull('image_url')
                     ->get(['id', 'visual_brief']);

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Upload the generated raw background image and process it.
     */
    public function uploadImage(Request $request, ImageOverlayService $overlayService)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $post = Post::find($request->post_id);
        
        if ($post->status !== 'generating_image') {
            return response()->json([
                'success' => false,
                'message' => 'Post is not in generating_image status.'
            ], 400);
        }

        try {
            // Process the image and apply overlay
            $mediaAsset = $overlayService->processAndSave($post, $request->file('image'));

            // Update post status
            $post->update([
                'status' => 'ready',
                'image_url' => asset('storage/' . $mediaAsset->file_path),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image processed and saved successfully.',
                'data' => [
                    'image_url' => $post->image_url
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Image processing failed: ' . $e->getMessage());
            $post->update([
                'status' => 'failed',
                'meta_data' => array_merge($post->meta_data ?? [], [
                    'image_error' => $e->getMessage()
                ])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process image: ' . $e->getMessage()
            ], 500);
        }
    }
}
