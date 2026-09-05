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
    public $tries = 1; // Requirement: $tries = 1 or from config

    public function __construct(int $postId, int $mediaAssetId, string $prompt)
    {
        $this->postId = $postId;
        $this->mediaAssetId = $mediaAssetId;
        $this->prompt = $prompt;
        
        $this->tries = config('services.cloudflare_ai.retry_times', 1);
    }

    public function handle(ImageGenerationProviderInterface $imageProvider): void
    {
        $post = Post::find($this->postId);
        $mediaAsset = MediaAsset::find($this->mediaAssetId);

        if (!$post || !$mediaAsset) {
            Log::error("GeneratePostImageJob missing post or media asset");
            return;
        }

        try {
            $result = $imageProvider->generate([
                'prompt' => $this->prompt
            ]);

            if (!$result['success']) {
                throw new \Exception($result['error_message'] ?? 'Image generation failed');
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

            DB::transaction(function () use ($post, $mediaAsset, $path, $mimeType, $extension, $fullPath, $imageSize, $result) {
                // Update media asset
                $metadata = $mediaAsset->metadata ?? [];
                $metadata['provider'] = $result['provider'] ?? 'cloudflare';
                $metadata['model'] = $result['model'] ?? 'unknown';

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

                // Handle post linking
                $isRegenerate = $metadata['regenerate'] ?? false;
                
                if ($isRegenerate) {
                    // Archive existing primary images
                    $post->media()->wherePivot('role', 'primary')->updateExistingPivot(
                        $post->media()->wherePivot('role', 'primary')->pluck('media_assets.id')->toArray(),
                        ['role' => 'archive']
                    );
                }
                
                // Attach new image as primary
                $post->media()->syncWithoutDetaching([
                    $mediaAsset->id => [
                        'position' => 0,
                        'role' => 'primary',
                    ]
                ]);

                // Update post status if it was in image generation step
                if ($post->status === Post::STATUS_GENERATING_IMAGE) {
                    $post->update(['status' => Post::STATUS_READY, 'generation_error' => null]);
                }
            });

        } catch (\Exception $e) {
            Log::error("GeneratePostImageJob Error for Media {$this->mediaAssetId}: " . $e->getMessage());
            
            $mediaAsset->update([
                'status' => 'failed',
                'metadata' => array_merge($mediaAsset->metadata ?? [], [
                    'error_code' => 'IMAGE_GENERATION_FAILED',
                    'error_message' => $e->getMessage()
                ])
            ]);

            $post->update([
                'generation_error' => $e->getMessage(),
                // Only change status to image_failed if we were actively generating
                // If this is a background regeneration, we probably shouldn't break the whole post if it already had an image.
                // But for simplicity according to spec:
                'status' => Post::STATUS_IMAGE_FAILED
            ]);

            $this->fail($e);
        }
    }
}
