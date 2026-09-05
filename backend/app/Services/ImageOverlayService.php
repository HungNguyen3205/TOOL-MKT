<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageOverlayService
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Overlay brand information onto a base image.
     *
     * @param string $baseImagePath Path in the storage disk (e.g. 'public/generated-images/...')
     * @param \App\Models\Brand $brand
     * @param string $title
     * @return bool True if successful
     */
    public function overlayBrandInfo(string $baseImagePath, $brand, string $title = ''): bool
    {
        try {
            $fullPath = Storage::disk('public')->path($baseImagePath);
            
            if (!file_exists($fullPath)) {
                Log::error("ImageOverlayService: Base image not found at $fullPath");
                return false;
            }

            $image = $this->manager->read($fullPath);
            $width = $image->width();
            $height = $image->height();

            // Overlay Logo if exists
            // Since we don't have a logo field in Brand, we can check for a default path
            $logoPath = Storage::disk('public')->path("brands/{$brand->id}/logo.png");
            if (file_exists($logoPath)) {
                $logo = $this->manager->read($logoPath);
                // Resize logo to be at most 15% of image width
                $logoTargetWidth = intval($width * 0.15);
                $logo->scaleDown(width: $logoTargetWidth);
                $image->place($logo, 'top-left', 20, 20);
            }

            // Overlay Hotline/Website at bottom
            $contactText = '';
            if ($brand->hotline) {
                $contactText .= "Hotline: {$brand->hotline}   ";
            }
            if ($brand->website) {
                $contactText .= "Web: {$brand->website}";
            }

            if (!empty($contactText)) {
                // Add a semi-transparent black rectangle at the bottom
                $barHeight = 50;
                $image->drawRectangle(0, $height - $barHeight, function ($rectangle) use ($width, $height, $barHeight) {
                    $rectangle->size($width, $barHeight);
                    $rectangle->background('rgba(0, 0, 0, 0.6)');
                });

                // Write text
                $image->text($contactText, $width / 2, $height - 25, function($font) {
                    // font size is proportional to width
                    $font->size(20);
                    $font->color('#ffffff');
                    $font->align('center');
                    $font->valign('middle');
                });
            }

            $image->save($fullPath);

            return true;
        } catch (\Exception $e) {
            Log::error("ImageOverlayService: Failed to overlay. " . $e->getMessage());
            return false;
        }
    }
}
