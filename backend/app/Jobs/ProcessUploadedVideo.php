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

class ProcessUploadedVideo implements ShouldQueue
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

            // Placeholder for FFmpeg extraction.
            // If FFMPEG is not available, we should catch it.
            // Let's assume MVP without strict FFMPEG requirement for now.
            
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
