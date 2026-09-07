<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\MediaAsset;
use App\Models\BrandAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\VideoGeneration\VideoBrandingService;
use Illuminate\Support\Str;
use Exception;

class ProcessVideoBrandingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    protected $post;
    protected $mediaAsset;

    public function __construct(Post $post, MediaAsset $mediaAsset)
    {
        $this->post = $post;
        $this->mediaAsset = $mediaAsset;
        $this->onQueue('video-processing');
    }

    public function handle(VideoBrandingService $brandingService): void
    {
        try {
            $brand = $this->post->brand;
            if (!$brand) {
                throw new Exception("Post does not have a brand associated.");
            }

            // Find the brand's active logo
            $logoAsset = BrandAsset::where('brand_id', $brand->id)
                ->where('asset_role', 'logo')
                ->where('is_active', true)
                ->with('mediaAsset')
                ->first();

            if (!$logoAsset || !$logoAsset->mediaAsset) {
                Log::warning("ProcessVideoBrandingJob: No active logo found for brand {$brand->id}. Skipping branding.");
                // We keep the final video as is, but maybe we should update some metadata
                return;
            }

            $sourceVideoPath = $this->mediaAsset->path;
            $logoPath = $logoAsset->mediaAsset->path;
            $uuid = Str::uuid();
            $outputVideoPath = "generated-videos/{$this->post->workspace_id}/{$this->post->id}/{$uuid}_branded.mp4";

            $success = $brandingService->addLogoToVideo($sourceVideoPath, $logoPath, $outputVideoPath);

            if (!$success) {
                throw new Exception("FFmpeg failed to add logo to video.");
            }

            // Save the branded video as a new MediaAsset
            $brandedAsset = MediaAsset::create([
                'brand_id' => $this->post->brand_id,
                'workspace_id' => $this->post->workspace_id,
                'type' => MediaAsset::TYPE_VIDEO,
                'status' => MediaAsset::STATUS_READY,
                'disk' => 'public',
                'path' => $outputVideoPath,
                'original_name' => 'branded.mp4',
                'stored_name' => 'branded.mp4',
                'mime_type' => 'video/mp4',
                'size_bytes' => 0,
                'checksum' => md5(uniqid()),
                'metadata' => [
                    'stage' => 'branded',
                    'original_video_id' => $this->mediaAsset->id,
                    'logo_asset_id' => $logoAsset->id,
                ]
            ]);

            // Link the branded video to the post
            $this->post->media()->attach($brandedAsset->id, [
                'role' => 'video_branded'
            ]);

            Log::info("ProcessVideoBrandingJob: Successfully branded video for post {$this->post->id}");
        } catch (Exception $e) {
            Log::error("ProcessVideoBrandingJob Failed: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
