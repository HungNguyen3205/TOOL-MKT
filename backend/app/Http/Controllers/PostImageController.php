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
            if ($request->input('regenerate')) {
                // Hủy các ảnh đang bị kẹt ở trạng thái processing
                MediaAsset::whereHas('posts', function($q) use ($post) {
                    $q->where('posts.id', $post->id);
                })
                ->where('type', 'image')
                ->where('status', 'processing')
                ->update(['status' => 'cancelled']);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'IMAGE_GENERATION_IN_PROGRESS',
                    'message' => 'An image is already being generated for this post.'
                ], 409);
            }
        }

        $prompt = $request->input('prompt');

        if (empty($prompt)) {
            // Build prompt
            $prompt = $this->buildPrompt($post);
        }

        $post->update([
            'status' => Post::STATUS_GENERATING_IMAGE,
            'image_prompt' => $prompt
        ]);

        // Lấy cookie từ request hoặc từ cấu hình hệ thống
        $providerCookie = $request->input('provider_cookie');
        if (empty($providerCookie)) {
            $providerCookie = \App\Models\Setting::where('key', 'PROVIDER_COOKIE')->value('value');
        }

        $mediaAsset = MediaAsset::create([
            'brand_id' => $post->brand_id,
            'workspace_id' => $post->workspace_id,
            'type' => MediaAsset::TYPE_IMAGE,
            'status' => MediaAsset::STATUS_PROCESSING,
            'disk' => 'public',
            'path' => 'pending/' . uniqid() . '.jpg',
            'original_name' => 'generated.jpg',
            'stored_name' => 'generated.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 0,
            'checksum' => md5(uniqid()),
            'metadata' => [
                'provider' => 'pollinations',
                'regenerate' => $request->input('regenerate', false),
                'provider_cookie' => $providerCookie
            ]
        ]);

        // Attach ngay vào post_media với role processing để frontend theo dõi
        $post->media()->attach($mediaAsset->id, [
            'role' => 'processing',
            'position' => 0
        ]);

        $post->update([
            'status' => Post::STATUS_GENERATING_IMAGE,
            'generation_error' => null,
        ]);

        GeneratePostImageJob::dispatch($post->id, $mediaAsset->id, $prompt);

        return response()->json([
            'success' => true,
            'message' => 'Đã đưa yêu cầu tạo ảnh vào hàng đợi.',
            'data' => [
                'post_id' => $post->id,
                'media_asset_id' => $mediaAsset->id,
                'status' => 'processing'
            ]
        ], 202);
    }

    public function status(Request $request, Post $post)
    {
        $mediaAssetId = $request->query('media_asset_id');

        $query = $post->media()->where('media_assets.type', 'image');
        
        if ($mediaAssetId) {
            $query->where('media_assets.id', $mediaAssetId);
        }
        
        $latestMedia = $query->orderBy('post_media.created_at', 'desc')->first();

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
                                ? url(Storage::disk($latestMedia->disk)->url($latestMedia->path)) . '?t=' . time()
                                : null,
                'error_code' => $latestMedia->metadata['error_code'] ?? null,
                'error_message' => $latestMedia->metadata['error_message'] ?? null
            ]
        ]);
    }

    protected function buildPrompt(Post $post)
    {
        if (!empty($post->image_prompt) && !str_starts_with($post->image_prompt, 'Title:')) {
            return $post->image_prompt;
        }

        // Tạo prompt tiếng Anh từ nội dung (hoặc ít nhất là thêm từ khóa cụ thể)
        $topic = $post->title ? "about " . $post->title : "about fitness and wellness";
        return "A high quality, professional photography, hyperrealistic style, {$topic}. Show relevant context (like gym, yoga studio, professional software dashboard). No random people unless they fit the context.";
    }
}
