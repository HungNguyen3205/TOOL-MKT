<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\ProcessUploadedImage;
use App\Jobs\ProcessUploadedVideo;

class MediaService
{
    /**
     * Upload and create initial MediaAsset record.
     */
    public function uploadMedia(UploadedFile $file, $brandId = null)
    {
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();
        $sizeBytes = $file->getSize();

        // Determine type
        $type = 'unknown';
        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } else {
            throw new \Exception('Invalid file type. Only images and videos are supported.');
        }

        // Store file with random name
        $storedName = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('media', $storedName, 'public');

        // Create MediaAsset record
        $mediaAsset = MediaAsset::create([
            'brand_id' => $brandId,
            'type' => $type,
            'status' => 'processing', // Will be updated by Queue
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => $sizeBytes,
            'checksum' => md5_file(Storage::disk('public')->path($path)),
            'title' => pathinfo($originalName, PATHINFO_FILENAME),
        ]);

        // Dispatch job based on type
        if ($type === 'image') {
            ProcessUploadedImage::dispatch($mediaAsset->id);
        } else {
            ProcessUploadedVideo::dispatch($mediaAsset->id);
        }

        return $mediaAsset;
    }
}
