<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GeminiImageService
{
    /**
     * Tự động tạo ảnh bằng Gemini API và chèn logo DANAVA
     *
     * @param Post $post
     * @return string|false
     */
    public function generateAndWatermark(Post $post)
    {
        try {
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                throw new \Exception('GEMINI_API_KEY is not configured in .env');
            }

            $model = env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image');
            $aspectRatio = env('GEMINI_IMAGE_ASPECT_RATIO', '4:5');
            $resolution = env('GEMINI_IMAGE_RESOLUTION', '2K');

            // Bố cục prompt theo quy tắc
            $prompt = $post->image_prompt ?? 'Gym, Yoga, Pilates, Pool, or Spa space. Modern, professional tech style. Navy blue, blue, white, and orange tones. 4:5 vertical layout. Clean empty space at the top right corner. No text, no QR codes, no fake logos, no real personal data.';
            
            // Thêm aspect ratio và resolution vào prompt nếu model mới không hỗ trợ parameter
            $fullPrompt = "{$prompt}. Aspect Ratio: {$aspectRatio}. Resolution: {$resolution}.";

            // Giao tiếp với Gemini Image API
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = Http::timeout(180)->post($endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $fullPrompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception('Gemini Image API Error: ' . $response->body());
            }

            $data = $response->json();
            
            // Xử lý base64 image trả về từ Gemini
            $imageBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
            
            if (!$imageBase64) {
                throw new \Exception('No image data returned from Gemini API. Response: ' . json_encode($data));
            }
            
            $imageContents = base64_decode($imageBase64);

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
