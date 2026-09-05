<?php

namespace App\Services\ImageGeneration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class PollinationsImageProvider implements ImageGenerationProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;
    protected string $size;
    protected string $quality;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.pollinations.api_key', '');
        $this->baseUrl = config('services.pollinations.base_url', 'https://gen.pollinations.ai');
        $this->model = config('services.pollinations.image_model', 'flux');
        $this->size = config('services.pollinations.image_size', '1024x1024');
        $this->quality = config('services.pollinations.image_quality', 'low');
        $this->timeout = (int) config('services.pollinations.timeout', 180);
    }

    public function generate(array $input): array
    {
        $prompt = $input['prompt'] ?? '';

        if (empty($prompt)) {
            return [
                'success' => false,
                'error_message' => 'Prompt cannot be empty.',
                'error_code' => 'EMPTY_PROMPT'
            ];
        }

        try {
            $encodedPrompt = urlencode($prompt);
            $sizeParts = explode('x', $this->size);
            $width = $sizeParts[0] ?? 1024;
            $height = $sizeParts[1] ?? 1024;
            $seed = rand(1, 999999999);
            
            // Sử dụng endpoint GET ổn định nhất của Pollinations
            $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&seed={$seed}&nologo=true";

            $response = Http::timeout($this->timeout)->get($url);

            if ($response->failed()) {
                $status = $response->status();
                return [
                    'success' => false,
                    'error_code' => 'POLLINATIONS_API_ERROR',
                    'error_message' => "Lỗi từ Pollinations (HTTP {$status})"
                ];
            }

            $imageData = $response->body();

            if (empty($imageData)) {
                return [
                    'success' => false,
                    'error_code' => 'POLLINATIONS_INVALID_RESPONSE',
                    'error_message' => 'Dữ liệu trả về không có ảnh.'
                ];
            }

            return [
                'success' => true,
                'image_data' => $imageData
            ];

        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'error_code' => 'POLLINATIONS_TIMEOUT',
                'error_message' => 'Quá thời gian kết nối (Timeout).'
            ];
        } catch (\Exception $e) {
            Log::error('PollinationsImageProvider generate failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error_code' => 'SYSTEM_ERROR',
                'error_message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ];
        }
    }

    public function verifyConnection(): array
    {
        return ['status' => true, 'message' => 'Pollinations ready'];
    }
}
