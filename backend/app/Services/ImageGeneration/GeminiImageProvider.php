<?php

namespace App\Services\ImageGeneration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiImageProvider implements ImageGenerationProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model = config('services.gemini.image_model', 'imagen-3.0-generate-001');
        $this->timeout = (int) config('services.gemini.timeout', 60);
    }

    public function generate(array $input): array
    {
        $prompt = $input['prompt'] ?? '';

        if (empty($prompt)) {
            return [
                'success' => false,
                'error_code' => 'EMPTY_PROMPT',
                'error_message' => 'Prompt cannot be empty.'
            ];
        }

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error_code' => 'MISSING_API_KEY',
                'error_message' => 'Gemini API key is not configured.'
            ];
        }

        try {
            // New endpoint format: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
            $url = rtrim($this->baseUrl, '/') . "/models/{$this->model}:generateContent";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE']
                ]
            ];

            Log::info("GEMINI_IMAGE_REQUEST", [
                'model' => $this->model,
                'prompt' => $prompt
            ]);

            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($url, $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->body();
                
                Log::error("GEMINI_IMAGE_API_ERROR", [
                    'status' => $status,
                    'body' => $body
                ]);

                $errorCode = 'GEMINI_API_ERROR';
                $errorMessage = 'Lỗi từ Gemini: ' . $body;

                if ($status === 401 || $status === 403) {
                    $errorCode = 'GEMINI_UNAUTHORIZED';
                    $errorMessage = 'API key không hợp lệ hoặc không có quyền truy cập model này.';
                } elseif ($status === 429) {
                    $errorCode = 'GEMINI_RATE_LIMIT';
                    $errorMessage = 'Vượt quá giới hạn request (Rate limit). Vui lòng thử lại sau.';
                } elseif ($status >= 500) {
                    $errorCode = 'GEMINI_SERVER_ERROR';
                    $errorMessage = 'Google API đang gặp sự cố (5xx).';
                }

                return [
                    'success' => false,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'http_status' => $status,
                    'metadata' => [
                        'provider' => 'gemini',
                        'model' => $this->model,
                        'http_status' => $status
                    ]
                ];
            }

            $responseData = $response->json();

            // Extract image from candidates -> content -> parts -> inlineData
            $candidates = $responseData['candidates'] ?? [];
            if (empty($candidates)) {
                Log::error("GEMINI_IMAGE_EMPTY_RESPONSE", ['response' => $responseData]);
                return [
                    'success' => false,
                    'error_code' => 'GEMINI_INVALID_RESPONSE',
                    'error_message' => 'API trả về thành công nhưng không có kết quả (candidates).',
                ];
            }

            $parts = $candidates[0]['content']['parts'] ?? [];
            $b64Json = '';
            $mimeType = 'image/jpeg';

            foreach ($parts as $part) {
                if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
                    $b64Json = $part['inlineData']['data'];
                    $mimeType = $part['inlineData']['mimeType'] ?? 'image/jpeg';
                    break;
                }
            }

            if (empty($b64Json)) {
                return [
                    'success' => false,
                    'error_code' => 'GEMINI_MISSING_IMAGE_DATA',
                    'error_message' => 'Không tìm thấy dữ liệu ảnh (inlineData) trong phản hồi của API.',
                ];
            }

            return [
                'success' => true,
                'image_data' => $b64Json, // Base64 string directly
                'metadata' => [
                    'provider' => 'gemini',
                    'model' => $this->model,
                    'mime_type' => $mimeType
                ]
            ];

        } catch (\Exception $e) {
            Log::error("GEMINI_IMAGE_EXCEPTION", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error_code' => 'GEMINI_CONNECTION_ERROR',
                'error_message' => 'Lỗi kết nối tới Gemini API: ' . $e->getMessage()
            ];
        }
    }

    public function verifyConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Gemini API key is not configured.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Configuration loaded. (Full API test requires generation)'
        ];
    }
}
