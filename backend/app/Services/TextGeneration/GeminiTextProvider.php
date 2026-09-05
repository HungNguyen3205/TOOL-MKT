<?php

namespace App\Services\TextGeneration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiTextProvider implements TextGenerationProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.text_model', 'gemini-2.5-flash-lite');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = (int) config('services.gemini.timeout', 60);
        $this->maxTokens = (int) config('services.gemini.max_output_tokens', 800);
    }

    public function generate(string $prompt, array $parameters = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error_code' => 'GEMINI_API_KEY_MISSING',
                'error_message' => 'API Key của Gemini chưa được cấu hình.'
            ];
        }

        try {
            $url = sprintf('%s/models/%s:generateContent?key=%s', $this->baseUrl, $this->model, $this->apiKey);

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'maxOutputTokens' => $this->maxTokens,
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post($url, $payload);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 401 || $status === 403) {
                    return ['success' => false, 'error_code' => 'GEMINI_UNAUTHORIZED', 'error_message' => 'API Key không hợp lệ.'];
                }
                if ($status === 429) {
                    return ['success' => false, 'error_code' => 'GEMINI_RATE_LIMIT', 'error_message' => 'Quá giới hạn rate limit của Gemini.'];
                }
                return ['success' => false, 'error_code' => 'GEMINI_API_ERROR', 'error_message' => 'Lỗi từ Gemini: ' . $response->body()];
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                return ['success' => false, 'error_code' => 'GEMINI_EMPTY_RESPONSE', 'error_message' => 'Gemini không trả về nội dung.'];
            }

            // Sanitize markdown code block if present
            $text = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', trim($text));

            $decoded = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                Log::error('Gemini Invalid JSON', ['raw' => $text, 'error' => json_last_msg()]);
                return ['success' => false, 'error_code' => 'GEMINI_INVALID_JSON', 'error_message' => 'Gemini trả về JSON không hợp lệ.'];
            }

            // Validate expected structure loosely
            if (!isset($decoded['title']) || !isset($decoded['content'])) {
                return ['success' => false, 'error_code' => 'GEMINI_MISSING_FIELDS', 'error_message' => 'JSON thiếu trường title hoặc content.'];
            }

            return [
                'success' => true,
                'data' => $decoded
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['success' => false, 'error_code' => 'GEMINI_TIMEOUT', 'error_message' => 'Hết thời gian chờ phản hồi từ Gemini.'];
        } catch (\Exception $e) {
            Log::error('Gemini Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'error_code' => 'GEMINI_SYSTEM_ERROR', 'error_message' => 'Lỗi hệ thống: ' . $e->getMessage()];
        }
    }
}
