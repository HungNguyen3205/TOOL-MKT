<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessUploadedImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mediaAssetId;

    public function __construct($mediaAssetId)
    {
        $this->mediaAssetId = $mediaAssetId;
    }

    public function handle(): void
    {
        $media = MediaAsset::find($this->mediaAssetId);
        if (!$media) return;

        try {
            $path = Storage::disk($media->disk)->path($media->path);
            
            if (!file_exists($path)) {
                throw new Exception("File not found at path: {$path}");
            }

            // In a real scenario, use Intervention Image to create variants
            // and get width/height. For now, use getimagesize()
            $imageInfo = @getimagesize($path);
            
            if ($imageInfo !== false) {
                $media->width = $imageInfo[0];
                $media->height = $imageInfo[1];
            } else {
                throw new Exception("MEDIA_FILE_CORRUPTED: Cannot read image dimensions.");
            }

            // TODO: Generate Thumbnail & Preview Variants using MediaVariant table

            $media->status = 'ready';
            $media->processed_at = now();
            $media->save();

        } catch (Exception $e) {
            $media->status = 'failed';
            $media->error_code = 'MEDIA_PROCESSING_FAILED';
            $media->error_message = $e->getMessage();
            $media->save();
        }
    }
}
