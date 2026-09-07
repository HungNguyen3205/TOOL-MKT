<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\MediaAsset;
use App\Services\ImageGeneration\ImageGenerationProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneratePostImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    protected $mediaAssetId;
    protected $prompt;
    
    public $timeout = 180;
    public $tries = 1;
    public $failOnTimeout = true;

    public function __construct(int $postId, int $mediaAssetId, string $prompt)
    {
        $this->postId = $postId;
        $this->mediaAssetId = $mediaAssetId;
        $this->prompt = $prompt;
        
        $this->tries = max(1, (int) config('services.pollinations.retry_times', 0) + 1);
    }

    public function handle(ImageGenerationProviderInterface $imageProvider): void
    {
        Log::info("IMAGE_JOB_STARTED", [
            'post_id' => $this->postId,
            'media_asset_id' => $this->mediaAssetId
        ]);

        $post = Post::find($this->postId);
        $mediaAsset = MediaAsset::find($this->mediaAssetId);

        if (!$post || !$mediaAsset) {
            Log::error("IMAGE_JOB_FAILED: GeneratePostImageJob missing post or media asset");
            $this->fail(new \Exception("GeneratePostImageJob missing post or media asset"));
            return;
        }

        $startTime = microtime(true);

        try {
            Log::info("POLLINATIONS_REQUEST_STARTED", ['media_asset_id' => $this->mediaAssetId]);
            $metadata = $mediaAsset->metadata ?? [];
            $regenerate = $metadata['regenerate'] ?? false;
            
            $input = [
                'prompt' => $this->prompt,
                'regenerate' => $regenerate
            ];

            if (!empty($metadata['provider_cookie'])) {
                $input['cookie'] = $metadata['provider_cookie'];
            }

            $result = $imageProvider->generate($input);
            Log::info("POLLINATIONS_RESPONSE_RECEIVED", [
                'media_asset_id' => $this->mediaAssetId, 
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
                'http_status' => $result['http_status'] ?? null
            ]);

            if (!$result['success']) {
                $errorMsg = $result['error_message'] ?? 'Image generation failed';
                $errorCode = $result['error_code'] ?? 'IMAGE_GENERATION_FAILED';
                
                // Add error details to media asset
                $mediaAsset->update([
                    'status' => 'failed',
                    'metadata' => array_merge($mediaAsset->metadata ?? [], [
                        'error_code' => $errorCode,
                        'error_message' => $errorMsg,
                        'http_status' => $result['http_status'] ?? null
                    ])
                ]);
                throw new \Exception($errorMsg);
            }

            $imageData = $result['image_data'];
            
            // Validate image using finfo
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);
            
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!array_key_exists($mimeType, $allowedMimes)) {
                throw new \Exception("IMAGE_DECODE_FAILED: Invalid mime type generated: {$mimeType}");
            }
            
            $extension = $allowedMimes[$mimeType];
            
            $workspaceId = $mediaAsset->workspace_id ?? 1;
            $uuid = Str::uuid()->toString();
            $path = "generated-images/{$workspaceId}/{$this->postId}/{$uuid}.{$extension}";

            $stored = Storage::disk('public')->put($path, $imageData);
            
            if (!$stored) {
                throw new \Exception("IMAGE_SAVE_FAILED: Could not save image to disk");
            }
            
            $fullPath = Storage::disk('public')->path($path);
            if (!file_exists($fullPath) || filesize($fullPath) <= 0) {
                Storage::disk('public')->delete($path);
                throw new \Exception("IMAGE_SAVE_FAILED: File is empty or not accessible");
            }

            $imageSize = @getimagesize($fullPath);
            if (!$imageSize) {
                Storage::disk('public')->delete($path);
                throw new \Exception("IMAGE_DECODE_FAILED: File is not a valid image");
            }

            Log::info("IMAGE_VALIDATED", ['media_asset_id' => $this->mediaAssetId, 'path' => $path]);

            // Overlay brand info safely
            try {
                $overlayService = app(\App\Services\ImageOverlayService::class);
                $brand = $post->brand ?? null;
                if ($brand) {
                    $overlayService->overlayBrandInfo($path, $brand, $post->title ?? '');
                    // Reload size after overlay
                    $imageSize = @getimagesize($fullPath);
                }
            } catch (\Exception $overlayEx) {
                Log::warning("Image Overlay Failed: " . $overlayEx->getMessage(), [
                    'post_id' => $this->postId,
                    'media_asset_id' => $this->mediaAssetId
                ]);
                // Tiếp tục, không fail job
            }

            Log::info("IMAGE_STORED", ['media_asset_id' => $this->mediaAssetId]);

            DB::transaction(function () use ($post, $mediaAsset, $path, $mimeType, $extension, $fullPath, $imageSize, $result) {
                // Update media asset
                $metadata = $mediaAsset->metadata ?? [];
                $metadata['provider'] = $result['metadata']['provider'] ?? 'pollinations';
                $metadata['model'] = $result['metadata']['model'] ?? 'unknown';
                $metadata['http_status'] = $result['metadata']['http_status'] ?? 200;

                $mediaAsset->update([
                    'status' => 'ready',
                    'disk' => 'public',
                    'path' => $path,
                    'stored_name' => basename($path),
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'size_bytes' => filesize($fullPath),
                    'width' => $imageSize[0],
                    'height' => $imageSize[1],
                    'checksum' => md5_file($fullPath),
                    'processed_at' => now(),
                    'metadata' => $metadata
                ]);

                // Archive old primary images
                $primaryIds = $post->media()
                    ->wherePivot('role', 'primary')
                    ->where('media_assets.id', '!=', $mediaAsset->id)
                    ->pluck('media_assets.id');
                
                foreach ($primaryIds as $pid) {
                    $post->media()->updateExistingPivot($pid, ['role' => 'archive']);
                }
                
                // Set new image as primary
                $pivot = $post->media()->where('media_assets.id', $mediaAsset->id)->first();
                if ($pivot) {
                    $post->media()->updateExistingPivot($mediaAsset->id, ['role' => 'primary', 'position' => 0]);
                } else {
                    $post->media()->attach($mediaAsset->id, ['role' => 'primary', 'position' => 0]);
                }

                // Update post status
                if ($post->status === Post::STATUS_GENERATING_IMAGE) {
                    $post->update(['status' => Post::STATUS_READY, 'generation_error' => null]);
                }
            });

            Log::info("IMAGE_JOB_COMPLETED", [
                'post_id' => $this->postId, 
                'media_asset_id' => $this->mediaAssetId,
                'duration_ms' => round((microtime(true) - $startTime) * 1000)
            ]);

        } catch (\Exception $e) {
            Log::error("IMAGE_JOB_FAILED: " . $e->getMessage(), [
                'post_id' => $this->postId,
                'media_asset_id' => $this->mediaAssetId
            ]);
            
            // Lỗi đã được ghi vào mediaAsset nếu là từ Provider
            // Đảm bảo ghi cho các lỗi catch khác (như decode fail, save fail)
            if ($mediaAsset && $mediaAsset->status !== 'failed') {
                $mediaAsset->update([
                    'status' => 'failed',
                    'metadata' => array_merge($mediaAsset->metadata ?? [], [
                        'error_code' => 'IMAGE_JOB_EXCEPTION',
                        'error_message' => $e->getMessage()
                    ])
                ]);
            }

            if ($post) {
                $post->update([
                    'generation_error' => $e->getMessage(),
                    'status' => Post::STATUS_IMAGE_FAILED
                ]);
            }

            $this->fail($e);
        }
    }
}
