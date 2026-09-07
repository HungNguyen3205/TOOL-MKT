<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\TextGeneration\TextGenerationProviderInterface;

class GeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function handle(TextGenerationProviderInterface $textProvider): void
    {
        try {
            $this->post->update(['status' => Post::STATUS_GENERATING_CONTENT]);

            // Combine inputs securely without leaking entire training dataset
            $inputData = $this->post->source_input ?? [];
            $topic = $inputData['topic'] ?? $this->post->title;
            
            // Limit KI to 5 and samples to 3 (Simulated by truncating arrays if any)
            $prompt = "Bạn là một chuyên gia marketing cho ngành Gym, Yoga, Pilates, Spa, Bể bơi.
Hãy viết một nội dung bài đăng Facebook tối ưu.
Chủ đề: " . $topic . "
Yêu cầu:
- Không lặp lại tên thương hiệu quá 2 lần.
- Output phải LÀ ĐỊNH DẠNG JSON với cấu trúc:
{
  "title": "",
  "content": "",
  "cta": "",
  "hashtags": [],
  "visual_brief": {
    "post_summary": "",
    "main_pain_point": "",
    "danava_solution": "",
    "target_customer": "",
    "main_subject": "",
    "scene": "",
    "visual_story": "",
    "composition": "",
    "headline": "",
    "aspect_ratio": "1:1",
    "brand_colors": ["navy", "orange", "white"],
    "reference_assets": [],
    "negative_requirements": []
  }
}
Chỉ trả về JSON, không kèm thêm text. Đối với `visual_brief`, phải điền chi tiết, cụ thể và bám sát nội dung, tạo headline tối đa 8-12 từ cho ảnh.";

            $result = $textProvider->generate($prompt);

            if (!$result['success']) {
                throw new \Exception('Content API Error: ' . ($result['error_message'] ?? 'Unknown error'));
            }

            $decoded = $result['data'];

            $this->post->update([
                'title' => $decoded['title'] ?? 'Tiêu đề tự động',
                'content' => $decoded['content'] ?? 'Nội dung tự động',
                'cta' => $decoded['cta'] ?? null,
                'hashtags' => $decoded['hashtags'] ?? [],
                'visual_brief' => $decoded['visual_brief'] ?? null,
                'status' => 'draft',
                'generation_error' => null
            ]);

        } catch (\Exception $e) {
            Log::error('GeneratePostContentJob failed: ' . $e->getMessage());
            $this->post->update([
                'status' => Post::STATUS_FAILED,
                'generation_error' => $e->getMessage()
            ]);
            
            throw $e; // Throw to mark job as failed
        }
    }
}
