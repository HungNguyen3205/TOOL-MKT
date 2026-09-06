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
                'error_code' => 'EMPTY_PROMPT',
                'error_message' => 'Prompt cannot be empty.'
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/') . '/v1/images/generations', [
                    'prompt' => $prompt,
                    'model' => $this->model,
                    'n' => 1,
                    'size' => $this->size,
                    'quality' => $this->quality,
                    'response_format' => 'b64_json',
                ]);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->body();
                
                $errorCode = 'POLLINATIONS_API_ERROR';
                $errorMessage = 'Lỗi từ Pollinations: ' . $body;

                if ($status === 401) {
                    $errorCode = 'POLLINATIONS_UNAUTHORIZED';
                    $errorMessage = 'API key thiếu hoặc không hợp lệ.';
                } elseif ($status === 402) {
                    $errorCode = 'POLLINATIONS_PAYMENT_REQUIRED';
                    $errorMessage = 'Hết Pollen hoặc hết ngân sách của key.';
                } elseif ($status === 403) {
                    $errorCode = 'POLLINATIONS_FORBIDDEN';
                    $errorMessage = 'Key không có quyền dùng model này.';
                } elseif ($status === 429) {
                    $errorCode = 'POLLINATIONS_RATE_LIMIT';
                    $errorMessage = 'Vượt giới hạn request (Rate limit).';
                } elseif ($status >= 500) {
                    $errorCode = 'POLLINATIONS_SERVER_ERROR';
                    $errorMessage = 'Pollinations tạm thời lỗi hệ thống (5xx).';
                }

                return [
                    'success' => false,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'http_status' => $status,
                    'metadata' => [
                        'provider' => 'pollinations',
                        'model' => $this->model,
                        'http_status' => $status
                    ]
                ];
            }

            $b64Json = $response->json('data.0.b64_json');

            if (empty($b64Json)) {
                return [
                    'success' => false,
                    'error_code' => 'POLLINATIONS_INVALID_RESPONSE',
                    'error_message' => 'Response không chứa ảnh hợp lệ (thiếu b64_json).'
                ];
            }

            $imageData = base64_decode($b64Json, true);

            if ($imageData === false || empty($imageData)) {
                return [
                    'success' => false,
                    'error_code' => 'POLLINATIONS_INVALID_RESPONSE',
                    'error_message' => 'Base64 decode thất bại hoặc dữ liệu rỗng.'
                ];
            }
            
            // Validate MIME TYPE (not just JSON error)
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);
            
            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
                return [
                    'success' => false,
                    'error_code' => 'POLLINATIONS_INVALID_RESPONSE',
                    'error_message' => 'Dữ liệu không phải là định dạng ảnh hợp lệ (MIME: ' . $mimeType . ').'
                ];
            }

            return [
                'success' => true,
                'image_data' => $imageData,
                'metadata' => [
                    'provider' => 'pollinations',
                    'model' => $this->model,
                    'http_status' => $response->status()
                ]
            ];

        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'error_code' => 'POLLINATIONS_TIMEOUT',
                'error_message' => 'Quá thời gian kết nối (Timeout) tới Pollinations.'
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
        try {
            // Test with a tiny prompt to verify authentication and model
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post(rtrim($this->baseUrl, '/') . '/v1/images/generations', [
                    'prompt' => 'test connection',
                    'model' => $this->model,
                    'n' => 1,
                    'size' => '256x256',
                    'quality' => 'low',
                    'response_format' => 'url', // just request URL to save payload size
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'provider' => 'pollinations',
                    'model' => $this->model,
                    'message' => 'Kết nối Pollinations thành công'
                ];
            }

            $status = $response->status();
            $msg = 'Lỗi kết nối';
            if ($status === 401) $msg = 'API key thiếu hoặc không hợp lệ.';
            if ($status === 402) $msg = 'Hết Pollen hoặc hết ngân sách.';
            if ($status === 403) $msg = 'Key không có quyền dùng model này.';
            if ($status === 429) $msg = 'Vượt giới hạn request (Rate limit).';
            
            return [
                'success' => false,
                'provider' => 'pollinations',
                'model' => $this->model,
                'message' => $msg,
                'http_status' => $status
            ];
            
        } catch (ConnectionException $e) {
             return [
                'success' => false,
                'provider' => 'pollinations',
                'model' => $this->model,
                'message' => 'Quá thời gian kết nối tới Pollinations.'
             ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => 'pollinations',
                'model' => $this->model,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ];
        }
    }
}
