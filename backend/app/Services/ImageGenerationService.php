<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageGenerationService
{
    /**
     * Generate an image using AI and apply the DANAVA logo.
     *
     * @param Post $post
     * @return string|false Final image path or false on failure
     */
    public function generateAndWatermark(Post $post)
    {
        try {
            // 1. Prepare prompt
            $prompt = $post->image_prompt ?? 'A modern, professional image related to Gym, Yoga, Pilates, Pool, or Spa. Colors: Navy blue, blue, white, and orange. Clean layout, 1080x1350 vertical aspect ratio.';
            
            // 2. Call OpenAI API to generate image
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                throw new \Exception('OPENAI_API_KEY is not configured in .env');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
            ]);

            if ($response->failed()) {
                throw new \Exception('Image API Error: ' . $response->body());
            }

            $data = $response->json();
            $imageUrl = $data['data'][0]['url'] ?? null;

            if (!$imageUrl) {
                throw new \Exception('No image URL returned from API');
            }

            // 3. Download the generated image
            $imageContents = file_get_contents($imageUrl);
            if (!Storage::disk('public')->exists('posts/raw')) {
                Storage::disk('public')->makeDirectory('posts/raw');
            }
            $tempName = Str::random(20) . '_raw.png';
            $tempPath = 'posts/raw/' . $tempName;
            Storage::disk('public')->put($tempPath, $imageContents);
            
            $post->update(['image_path' => $tempPath]);

            // 4. Process image with Intervention Image (resize to 1080x1350 and add logo)
            $manager = new ImageManager(new Driver());
            $image = $manager->read(Storage::disk('public')->path($tempPath));

            // Resize and crop to 1080x1350 (Vertical)
            $image->cover(1080, 1350);

            // Add logo (assuming logo exists at public/images/logo.png)
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                $logo = $manager->read($logoPath);
                // Resize logo to fit nicely, e.g. 200px wide
                $logo->scale(width: 200);
                
                // Insert logo at bottom-right corner with 20px padding
                $image->place($logo, 'bottom-right', 20, 20);
            }

            // 5. Save the final image
            if (!Storage::disk('public')->exists('posts/final')) {
                Storage::disk('public')->makeDirectory('posts/final');
            }
            $finalName = Str::random(20) . '_final.jpg';
            $finalRelativePath = 'posts/final/' . $finalName;
            
            $image->toJpeg(90)->save(Storage::disk('public')->path($finalRelativePath));

            // 6. Update DB
            $post->update([
                'final_image_path' => $finalRelativePath,
                'status' => Post::STATUS_READY,
                'generation_error' => null
            ]);

            return $finalRelativePath;

        } catch (\Exception $e) {
            $post->update([
                'status' => Post::STATUS_FAILED,
                'generation_error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
