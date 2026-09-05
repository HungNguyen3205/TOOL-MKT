<?php

namespace App\Providers\AiProvider;

use App\Contracts\ContentAiProviderInterface;
use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Exceptions\HttpResponseException;

class GeminiContentProvider implements ContentAiProviderInterface
{
    public function generate(ContentGenerationData $data, array $promptData, bool $isRetry = false, array $retryErrors = []): ContentGenerationResult
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            $this->throwError('GEMINI_API_KEY_MISSING', 'Thiếu API Key Gemini trong cấu hình.', 500);
        }

        $model = config('services.gemini.text_model', 'gemini-1.5-flash');
        $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $timeout = config('services.gemini.timeout', 60);

        $systemMsg = $promptData['system'] . "\nAlways output valid JSON strictly adhering to the schema.";
        $userMsg = $promptData['user'];

        if ($isRetry && !empty($retryErrors)) {
            $userMsg .= "\n\nCẢNH BÁO: Trong lần phản hồi trước, kết quả của bạn bị các lỗi sau:\n- " . implode("\n- ", $retryErrors) . "\n\nHãy sửa lại các lỗi trên, TUYỆT ĐỐI KHÔNG tự bịa thêm thông tin ngoài yêu cầu. Giữ nguyên cấu trúc JSON.";
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout($timeout)
              ->post("{$baseUrl}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemMsg . "\n\n" . $userMsg]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'responseMimeType' => 'application/json',
                ]
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out')) {
                $this->throwError('GEMINI_TIMEOUT', 'Gemini phản hồi quá thời gian cho phép.', 504);
            }
            $this->throwError('GEMINI_CONNECTION_FAILED', 'Không thể kết nối với Gemini.', 502);
        } catch (\Exception $e) {
            $this->throwError('ALL_AI_PROVIDERS_FAILED', 'Có lỗi xảy ra khi kết nối Gemini.', 500);
        }

        if (!$response->successful()) {
            if ($response->status() == 400 || $response->status() == 401) {
                $this->throwError('GEMINI_AUTHENTICATION_FAILED', 'Gemini API Key không hợp lệ hoặc sai request.', 401);
            }
            if ($response->status() == 429) {
                $this->throwError('GEMINI_RATE_LIMITED', 'Rate Limit từ Gemini.', 429);
            }
            $this->throwError('ALL_AI_PROVIDERS_FAILED', 'Lỗi từ dịch vụ Gemini: ' . $response->body(), 500);
        }

        $result = $response->json();
        
        $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        
        if (empty($content)) {
            $this->throwError('GEMINI_INVALID_RESPONSE', 'Phản hồi từ Gemini bị rỗng.', 502);
        }

        // Clean up code blocks if Gemini returns markdown json
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);

        $parsed = json_decode($content, true);

        // If the parsed JSON doesn't have a 'versions' key but is an array or object, we wrap it
        if ($parsed && !isset($parsed['versions'])) {
            if (isset($parsed[0])) { // array of versions
                $parsed = ['versions' => $parsed];
            } else if (isset($parsed['title'])) { // single version
                $parsed = ['versions' => [$parsed]];
            }
        }

        if (!$parsed || !isset($parsed['versions'])) {
            $this->throwError('GEMINI_INVALID_RESPONSE', 'Gemini trả về JSON sai schema hoặc thiếu phiên bản.', 502);
        }

        $inputTokens = $result['usageMetadata']['promptTokenCount'] ?? null;
        $outputTokens = $result['usageMetadata']['candidatesTokenCount'] ?? null;
        $totalTokens = $result['usageMetadata']['totalTokenCount'] ?? null;

        return new ContentGenerationResult(
            versions: $parsed['versions'],
            metadata: [
                'model' => $model,
                'provider' => 'gemini',
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
