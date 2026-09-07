<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\MediaAsset;
use App\Jobs\GenerateVideoDraftJob;
use App\Jobs\GenerateVideoFinalJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VideoGenerationController extends Controller
{
    public function __construct()
    {
        // Chặn nếu tính năng chưa bật
        if (!env('VIDEO_GENERATION_ENABLED', false)) {
            abort(403, 'Video generation is currently disabled.');
        }
    }

    public function generateDraft(Request $request, Post $post)
    {
        $request->validate([
            'reference_assets' => 'array|max:3',
            'reference_assets.*.path' => 'required|string',
            'reference_assets.*.role' => 'required|string',
            'provider_cookie' => 'nullable|string'
        ]);

        // Lấy cookie từ request hoặc từ cấu hình hệ thống
        $providerCookie = $request->input('provider_cookie');
        if (empty($providerCookie)) {
            $providerCookie = \App\Models\Setting::where('key', 'PROVIDER_COOKIE')->value('value');
        }

        // Tạo media asset mới để theo dõi tiến độ draft
        $mediaAsset = MediaAsset::create([
            'brand_id' => $post->brand_id,
            'workspace_id' => $post->workspace_id,
            'type' => MediaAsset::TYPE_VIDEO,
            'status' => MediaAsset::STATUS_VIDEO_DRAFT_QUEUED,
            'disk' => 'public',
            'path' => 'pending/video_draft_' . uniqid() . '.mp4',
            'original_name' => 'draft.mp4',
            'stored_name' => 'draft.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 0,
            'checksum' => md5(uniqid()),
            'metadata' => [
                'stage' => 'draft',
                'provider_cookie' => $providerCookie
            ]
        ]);

        $post->media()->attach($mediaAsset->id, ['role' => 'video_draft']);

        GenerateVideoDraftJob::dispatch($post, $mediaAsset, $request->input('reference_assets', []));

        return response()->json([
            'message' => 'Video draft generation started',
            'media_asset_id' => $mediaAsset->id
        ], 202);
    }

    public function getStatus(Post $post)
    {
        // Lấy tất cả video assets của bài viết
        $assets = $post->media()->where('type', MediaAsset::TYPE_VIDEO)
            ->withPivot('role')
            ->orderBy('post_media.created_at', 'desc')
            ->get()
            ->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'status' => $asset->status,
                    'stage' => $asset->metadata['stage'] ?? 'unknown',
                    'path' => $asset->path ? asset('storage/' . $asset->path) : null,
                    'error_message' => $asset->error_message,
                    'role' => $asset->pivot->role ?? null
                ];
            });

        return response()->json(['assets' => $assets]);
    }

    public function generateFinal(Request $request, Post $post)
    {
        $request->validate([
            'reference_assets' => 'array|max:3',
            'provider_cookie' => 'nullable|string'
        ]);

        // Kiểm tra xem đã có bản draft nào được duyệt (hoặc ít nhất là ready) chưa
        $drafts = $post->media()->where('type', MediaAsset::TYPE_VIDEO)->where('status', MediaAsset::STATUS_VIDEO_DRAFT_READY)->count();
        if ($drafts === 0) {
            return response()->json(['message' => 'No ready draft found to generate final video'], 400);
        }

        // Lấy cookie từ request hoặc từ cấu hình hệ thống
        $providerCookie = $request->input('provider_cookie');
        if (empty($providerCookie)) {
            $providerCookie = \App\Models\Setting::where('key', 'PROVIDER_COOKIE')->value('value');
        }

        $mediaAsset = MediaAsset::create([
            'brand_id' => $post->brand_id,
            'workspace_id' => $post->workspace_id,
            'type' => MediaAsset::TYPE_VIDEO,
            'status' => MediaAsset::STATUS_VIDEO_FINAL_QUEUED,
            'disk' => 'public',
            'path' => 'pending/video_final_' . uniqid() . '.mp4',
            'original_name' => 'final.mp4',
            'stored_name' => 'final.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 0,
            'checksum' => md5(uniqid()),
            'metadata' => [
                'stage' => 'final',
                'provider_cookie' => $providerCookie
            ]
        ]);

        $post->media()->attach($mediaAsset->id, ['role' => 'video_final']);

        GenerateVideoFinalJob::dispatch($post, $mediaAsset, $request->input('reference_assets', []));

        return response()->json([
            'message' => 'Video final generation started',
            'media_asset_id' => $mediaAsset->id
        ], 202);
    }
}
