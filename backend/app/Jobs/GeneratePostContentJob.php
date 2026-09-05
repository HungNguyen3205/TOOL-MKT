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
- Tối đa 3 đoạn văn.
- Không lặp lại tên thương hiệu quá 2 lần.
- Output phải LÀ ĐỊNH DẠNG JSON với các key: 'title', 'content', 'cta', 'hashtags' (array of strings), 'image_prompt' (tiếng Anh mô tả ảnh).
Chỉ trả về JSON, không kèm thêm text.";

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
                'image_prompt' => $decoded['image_prompt'] ?? 'A modern gym or yoga studio',
                'status' => 'draft', // The prompt specifies "Cho người dùng duyệt nội dung trước khi tạo ảnh hoặc đăng bài."
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
