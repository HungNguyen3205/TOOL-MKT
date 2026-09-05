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

class GeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function handle(): void
    {
        try {
            $this->post->update(['status' => Post::STATUS_GENERATING_CONTENT]);

            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                throw new \Exception('OPENAI_API_KEY is not configured in .env');
            }

            $prompt = "Bạn là một chuyên gia marketing cho ngành Gym, Yoga, Pilates, Spa, Bể bơi. 
Hãy tạo một nội dung bài viết bán hàng / chia sẻ kiến thức dựa trên thông tin: " . json_encode($this->post->source_input ?? []) . ".
Yêu cầu trả về định dạng JSON với các trường:
- title: Tiêu đề bài viết
- content: Nội dung bài viết
- hashtags: Mảng các chuỗi hashtag
- image_prompt: Tiếng Anh. Prompt chi tiết để tạo hình ảnh AI (DALL-E) bám sát nội dung, tone màu chủ đạo Navy blue, blue, white, orange, bố cục hiện đại, không có chữ text trong hình.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You must always return a valid JSON object.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->failed()) {
                throw new \Exception('Content API Error: ' . $response->body());
            }

            $result = json_decode($response->json('choices.0.message.content'), true);

            $this->post->update([
                'title' => $result['title'] ?? 'Tiêu đề tự động',
                'content' => $result['content'] ?? 'Nội dung tự động',
                'hashtags' => $result['hashtags'] ?? [],
                'image_prompt' => $result['image_prompt'] ?? 'A modern gym or yoga studio',
                'status' => Post::STATUS_GENERATING_IMAGE,
                'generation_error' => null
            ]);

            // Dispatch job to generate image
            GeneratePostImageJob::dispatch($this->post);

        } catch (\Exception $e) {
            Log::error('GeneratePostContentJob failed: ' . $e->getMessage());
            $this->post->update([
                'status' => Post::STATUS_FAILED,
                'generation_error' => $e->getMessage()
            ]);
        }
    }
}
