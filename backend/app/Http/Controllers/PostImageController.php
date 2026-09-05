<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\MediaAsset;
use App\Jobs\GeneratePostImageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostImageController extends Controller
{
    public function generate(Request $request, Post $post)
    {
        $request->validate([
            'prompt' => 'nullable|string|max:5000',
            'regenerate' => 'boolean'
        ]);

        // Check if there is already a processing image for this post
        $isProcessing = $post->media()
            ->where('media_assets.status', 'processing')
            ->where('media_assets.type', 'image')
            ->exists();

        if ($isProcessing) {
            return response()->json([
                'success' => false,
                'error_code' => 'IMAGE_GENERATION_IN_PROGRESS',
                'message' => 'An image is already being generated for this post.'
            ], 409);
        }

        $prompt = $request->input('prompt');

        if (empty($prompt)) {
            // Build prompt
            $prompt = $this->buildPrompt($post);
        }

        // We use GeneratePostImageJob. We need to tell it to generate.
        // It will create the MediaAsset inside the job, or we can create it here and pass ID.
        // It's safer to create it here so we have a record immediately with 'processing' status.
        $mediaAsset = MediaAsset::create([
            'workspace_id' => $post->workspace_id ?? 1, // Fallback to 1 if not set
            'type' => 'image',
            'status' => 'processing',
            'disk' => 'public',
            'path' => 'pending',
            'original_name' => 'AI Generated',
            'stored_name' => 'pending',
            'mime_type' => 'pending',
            'size_bytes' => 0,
            'checksum' => 'pending',
            'metadata' => [
                'provider' => 'cloudflare',
                'regenerate' => $request->input('regenerate', false)
            ]
        ]);

        GeneratePostImageJob::dispatch($post->id, $mediaAsset->id, $prompt)->onQueue('image-generation');

        return response()->json([
            'success' => true,
            'message' => 'Đã đưa yêu cầu tạo ảnh vào hàng đợi.',
            'data' => [
                'post_id' => $post->id,
                'status' => 'processing'
            ]
        ], 202);
    }

    public function status(Post $post)
    {
        // Find the latest processing or just completed media asset
        // We will look for the most recently created image for this post.
        // Wait, if it's processing, it might not be attached to post_media yet if we attach it AFTER success.
        // Let's modify generate() to attach it immediately with role 'processing' or just query MediaAsset if we save post_id.
        // But MediaAsset table doesn't have post_id, it's a many-to-many via post_media.
        // So we MUST attach it in generate().
        
        $latestMedia = $post->media()
            ->where('media_assets.type', 'image')
            ->orderBy('post_media.created_at', 'desc')
            ->first();

        if (!$latestMedia) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => null,
                    'image_url' => null,
                    'error_code' => null,
                    'error_message' => null
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $latestMedia->status,
                'image_url' => $latestMedia->status === 'ready' && $latestMedia->path 
                                ? url(Storage::disk($latestMedia->disk)->url($latestMedia->path)) 
                                : null,
                'error_code' => $latestMedia->metadata['error_code'] ?? null,
                'error_message' => $latestMedia->metadata['error_message'] ?? null
            ]
        ]);
    }

    protected function buildPrompt(Post $post)
    {
        // Tự tạo prompt từ tiêu đề, nội dung, cta, thương hiệu
        $elements = [];
        if ($post->title) $elements[] = "Title: " . $post->title;
        if ($post->tone) $elements[] = "Tone: " . $post->tone;
        $elements[] = "A high quality, professional photography, hyperrealistic style.";
        
        return implode(". ", $elements);
    }
}
