<?php

namespace App\Services;

use App\Models\Post;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOverlayService
{
    /**
     * Process raw background image, overlay logo and headline, and save.
     */
    public function processAndSave(Post $post, $uploadedFile)
    {
        $brand = $post->brand;
        
        // 1. Save original raw image
        $originalFilename = 'raw_' . Str::random(10) . '.' . $uploadedFile->getClientOriginalExtension();
        $originalPath = $uploadedFile->storeAs('posts/raw', $originalFilename, 'public');

        // 2. Load the image into GD
        $imagePath = Storage::disk('public')->path($originalPath);
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $image = @imagecreatefromjpeg($imagePath);
        } elseif ($ext === 'png') {
            $image = @imagecreatefrompng($imagePath);
        } elseif ($ext === 'webp') {
            $image = @imagecreatefromwebp($imagePath);
        } else {
            throw new \Exception("Unsupported image format");
        }

        if (!$image) {
            throw new \Exception("Could not read image file");
        }

        $imgWidth = imagesx($image);
        $imgHeight = imagesy($image);

        // 3. Overlay Logo
        if ($brand && $brand->logo_path && Storage::disk('public')->exists($brand->logo_path)) {
            $logoPath = Storage::disk('public')->path($brand->logo_path);
            $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            
            $logo = null;
            if ($logoExt === 'png') $logo = @imagecreatefrompng($logoPath);
            elseif ($logoExt === 'jpg' || $logoExt === 'jpeg') $logo = @imagecreatefromjpeg($logoPath);
            elseif ($logoExt === 'webp') $logo = @imagecreatefromwebp($logoPath);

            if ($logo) {
                $logoWidth = imagesx($logo);
                $logoHeight = imagesy($logo);

                // Calculate target logo size (e.g. 15% of image width)
                $targetLogoWidth = $imgWidth * 0.15;
                $targetLogoHeight = ($logoHeight / $logoWidth) * $targetLogoWidth;

                // Calculate position (top right, 5% margin)
                $margin = $imgWidth * 0.05;
                $xPos = $imgWidth - $targetLogoWidth - $margin;
                $yPos = $margin;

                // Create a temporary image for resized logo
                $resizedLogo = imagecreatetruecolor($targetLogoWidth, $targetLogoHeight);
                
                // Preserve transparency
                imagealphablending($resizedLogo, false);
                imagesavealpha($resizedLogo, true);
                $transparent = imagecolorallocatealpha($resizedLogo, 255, 255, 255, 127);
                imagefilledrectangle($resizedLogo, 0, 0, $targetLogoWidth, $targetLogoHeight, $transparent);

                // Resize and copy
                imagecopyresampled($resizedLogo, $logo, 0, 0, 0, 0, $targetLogoWidth, $targetLogoHeight, $logoWidth, $logoHeight);

                // Overlay onto main image
                imagealphablending($image, true);
                imagecopy($image, $resizedLogo, $xPos, $yPos, 0, 0, $targetLogoWidth, $targetLogoHeight);

                imagedestroy($logo);
                imagedestroy($resizedLogo);
            }
        }

        // 4. Overlay Headline
        $visualBrief = $post->visual_brief;
        $headline = $visualBrief['headline'] ?? null;
        if ($headline) {
            $fontPath = storage_path('app/fonts/times.ttf');
            if (file_exists($fontPath)) {
                // Determine font size based on image width (e.g. 5% of width)
                $fontSize = $imgWidth * 0.05;
                $textColor = imagecolorallocate($image, 255, 255, 255); // White text
                $shadowColor = imagecolorallocate($image, 0, 0, 0); // Black shadow

                // Very basic word wrapping for headline (max 8-12 words normally, so maybe max length)
                $lines = explode('|', wordwrap($headline, 40, '|'));
                
                // Bottom center positioning
                $y = $imgHeight - ($imgHeight * 0.15); // 15% from bottom

                foreach ($lines as $line) {
                    $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
                    $textWidth = $bbox[2] - $bbox[0];
                    $x = ($imgWidth - $textWidth) / 2; // Center horizontally

                    // Draw shadow
                    imagettftext($image, $fontSize, 0, $x + 2, $y + 2, $shadowColor, $fontPath, $line);
                    // Draw text
                    imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $line);

                    $y += ($fontSize * 1.5); // Line height
                }
            }
        }

        // 5. Save final image
        $finalFilename = 'final_' . Str::random(10) . '.jpg';
        $finalPath = 'posts/final/' . $finalFilename;
        $finalDiskPath = Storage::disk('public')->path($finalPath);

        // Ensure directory exists
        if (!Storage::disk('public')->exists('posts/final')) {
            Storage::disk('public')->makeDirectory('posts/final');
        }

        imagejpeg($image, $finalDiskPath, 90);
        imagedestroy($image);

        // 6. Create MediaAsset
        $mediaAsset = MediaAsset::create([
            'post_id' => $post->id,
            'file_path' => $finalPath,
            'file_type' => 'image',
            'file_size' => filesize($finalDiskPath),
        ]);

        return $mediaAsset;
    }
}
