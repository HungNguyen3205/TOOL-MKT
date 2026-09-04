<?php

namespace App\Providers\AiProvider;

use App\Contracts\ContentAiProviderInterface;
use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Exceptions\HttpResponseException;

class OpenAiContentProvider implements ContentAiProviderInterface
{
    public function generate(ContentGenerationData $data, array $promptData, bool $isRetry = false, array $retryErrors = []): ContentGenerationResult
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            $this->throwError('OPENAI_API_KEY_MISSING', 'Thiếu API Key OpenAI trong cấu hình.', 500);
        }

        $model = config('services.openai.model');
        $timeout = config('services.openai.timeout');
        $maxTokens = config('services.openai.max_output_tokens');

        $systemMsg = $promptData['system'];
        $userMsg = $promptData['user'];

        if ($isRetry && !empty($retryErrors)) {
            $userMsg .= "\n\nCẢNH BÁO: Trong lần phản hồi trước, kết quả của bạn bị các lỗi sau:\n- " . implode("\n- ", $retryErrors) . "\n\nHãy sửa lại các lỗi trên, TUYỆT ĐỐI KHÔNG tự bịa thêm thông tin ngoài yêu cầu. Giữ nguyên cấu trúc JSON.";
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemMsg],
                        ['role' => 'user', 'content' => $userMsg],
                    ],
                    'max_tokens' => (int) $maxTokens,
                    'temperature' => 0.7,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'facebook_content_versions',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'versions' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'title' => ['type' => 'string'],
                                                'content' => ['type' => 'string'],
                                                'cta' => ['type' => 'string'],
                                                'hashtags' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string']
                                                ]
                                            ],
                                            'required' => ['title', 'content', 'cta', 'hashtags'],
                                            'additionalProperties' => false
                                        ]
                                    ]
                                ],
                                'required' => ['versions'],
                                'additionalProperties' => false
                            ]
                        ]
                    ]
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out')) {
                $this->throwError('OPENAI_TIMEOUT', 'OpenAI phản hồi quá thời gian cho phép.', 504);
            }
            $this->throwError('OPENAI_CONNECTION_FAILED', 'Không thể kết nối với OpenAI.', 502);
        } catch (\Exception $e) {
            $this->throwError('ALL_AI_PROVIDERS_FAILED', 'Có lỗi xảy ra khi kết nối OpenAI.', 500);
        }

        if (!$response->successful()) {
            if ($response->status() == 401) {
                $this->throwError('OPENAI_AUTHENTICATION_FAILED', 'OpenAI API Key không hợp lệ.', 401);
            }
            if ($response->status() == 429) {
                $errorData = $response->json();
                if (isset($errorData['error']['code']) && $errorData['error']['code'] === 'insufficient_quota') {
                    $this->throwError('OPENAI_QUOTA_EXCEEDED', 'Hết Quota OpenAI.', 429);
                }
                $this->throwError('OPENAI_RATE_LIMITED', 'Rate Limit từ OpenAI.', 429);
            }
            $this->throwError('ALL_AI_PROVIDERS_FAILED', 'Lỗi từ dịch vụ OpenAI: ' . $response->body(), 500);
        }

        $result = $response->json();
        
        if (empty($result['choices'][0]['message']['content'])) {
            $this->throwError('OPENAI_INVALID_RESPONSE', 'Phản hồi từ OpenAI bị rỗng.', 502);
        }

        $rawJson = $result['choices'][0]['message']['content'];
        $parsed = json_decode($rawJson, true);

        if (!$parsed || !isset($parsed['versions']) || count($parsed['versions']) !== $data->numberOfVersions) {
            $this->throwError('OPENAI_INVALID_RESPONSE', 'OpenAI trả về JSON sai schema hoặc thiếu phiên bản.', 502);
        }

        $usage = $result['usage'] ?? [];
        
        $inputTokens = $usage['prompt_tokens'] ?? null;
        $outputTokens = $usage['completion_tokens'] ?? null;
        $totalTokens = $usage['total_tokens'] ?? null;

        return new ContentGenerationResult(
            versions: $parsed['versions'],
            metadata: [
                'model' => $model,
                'provider' => 'openai',
                'generated_at' => Carbon::now()->toIso8601String(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
            ]
        );
    }

    private function throwError($code, $message, $status)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $code
        ], $status));
    }
}
