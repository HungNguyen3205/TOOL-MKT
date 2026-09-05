<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CloudflareImageService
{
    /**
     * Tự động tạo ảnh bằng Cloudflare Workers AI API và chèn logo DANAVA
     *
     * @param Post $post
     * @return string|false
     */
    public function generateAndWatermark(Post $post)
    {
        try {
            $accountId = env('CLOUDFLARE_ACCOUNT_ID');
            $token = env('CLOUDFLARE_AI_TOKEN');
            
            if (!$accountId || !$token) {
                throw new \Exception('CLOUDFLARE_ACCOUNT_ID or CLOUDFLARE_AI_TOKEN is not configured in .env');
            }

            $model = env('CLOUDFLARE_IMAGE_MODEL', '@cf/bytedance/stable-diffusion-xl-lightning');

            // Bố cục prompt theo quy tắc
            $prompt = $post->image_prompt ?? 'Gym, Yoga, Pilates, Pool, or Spa space. Modern, professional tech style. Navy blue, blue, white, and orange tones. 4:5 vertical layout. Clean empty space at the top right corner. No text, no QR codes, no fake logos, no real personal data.';

            // Giao tiếp với Cloudflare Image API
            $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";
            
            $response = Http::withToken($token)
                ->timeout(180)
                ->post($endpoint, [
                    'prompt' => $prompt
                ]);

            if ($response->failed()) {
                throw new \Exception('Cloudflare Image API Error (Status ' . $response->status() . '): ' . $response->body());
            }
            
            $imageContents = $response->body();
            
            if (empty($imageContents) || !str_contains($response->header('Content-Type') ?? '', 'image')) {
                throw new \Exception('Invalid image response from Cloudflare API: ' . substr($imageContents, 0, 200));
            }

            // Lưu file nguyên bản
            if (!Storage::disk('public')->exists('posts/raw')) {
                Storage::disk('public')->makeDirectory('posts/raw');
            }
            $tempName = Str::random(20) . '_raw.png';
            $tempPath = 'posts/raw/' . $tempName;
            Storage::disk('public')->put($tempPath, $imageContents);
            
            $post->update(['image_path' => $tempPath]);

            // Chèn logo bằng Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->read(Storage::disk('public')->path($tempPath));

            $logoPath = storage_path('app/branding/danava-logo.png');
            if (file_exists($logoPath)) {
                $logo = $manager->read($logoPath);
                
                // Đặt logo ở góc trên bên phải (top-right) cách mép 40-60px
                $image->place($logo, 'top-right', 50, 50);
            }

            // Lưu ảnh hoàn chỉnh
            if (!Storage::disk('public')->exists('posts/final')) {
                Storage::disk('public')->makeDirectory('posts/final');
            }
            $finalName = Str::random(20) . '_final.jpg';
            $finalRelativePath = 'posts/final/' . $finalName;
            
            $image->toJpeg(90)->save(Storage::disk('public')->path($finalRelativePath));

            // Cập nhật trạng thái
            $post->update([
                'final_image_path' => $finalRelativePath,
                'status' => Post::STATUS_READY,
                'generation_error' => null
            ]);

            return $finalRelativePath;

        } catch (\Exception $e) {
            $post->update([
                'status' => Post::STATUS_IMAGE_FAILED,
                'generation_error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
