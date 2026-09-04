<?php

namespace App\Providers\AiProvider;

use App\Contracts\ContentAiProviderInterface;
use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class OllamaContentProvider implements ContentAiProviderInterface
{
    public function generate(ContentGenerationData $data, array $promptData, bool $isRetry = false, array $retryErrors = []): ContentGenerationResult
    {
        $prompt = $promptData['full_prompt'];
        
        if ($isRetry && !empty($retryErrors)) {
            $prompt .= "\n\nCẢNH BÁO: Trong lần phản hồi trước, kết quả của bạn bị các lỗi sau:\n- " . implode("\n- ", $retryErrors) . "\n\nHãy sửa lại các lỗi trên thành JSON hợp lệ theo đúng schema được yêu cầu.";
        }

        $baseUrl = config('services.ollama.base_url');
        $model = config('services.ollama.model');
        $timeout = config('services.ollama.timeout');

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)->post("{$baseUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json'
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                $this->throwError('OLLAMA_TIMEOUT', 'AI phản hồi quá thời gian cho phép. Hãy thử lại với nội dung ngắn hơn.', 504);
            }
            $this->throwError('OLLAMA_CONNECTION_FAILED', 'Không thể kết nối với Ollama. Hãy kiểm tra dịch vụ Ollama và thử lại.', 502);
        } catch (\Exception $e) {
            $this->throwError('ALL_AI_PROVIDERS_FAILED', 'Có lỗi xảy ra khi kết nối AI.', 500);
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            if ($response->status() == 404) {
                $this->throwError('OLLAMA_MODEL_NOT_FOUND', 'Không tìm thấy model AI được cấu hình. Hãy kiểm tra model Ollama.', 404);
            }
            $this->throwError('ALL_AI_PROVIDERS_FAILED', 'Lỗi không xác định từ dịch vụ AI.', 500);
        }

        $result = $response->json();
        if (empty($result['response'])) {
            $this->throwError('OLLAMA_INVALID_RESPONSE', 'Phản hồi từ AI không chứa dữ liệu.', 502);
        }

        $rawJson = $this->cleanMarkdownJson($result['response']);
        $parsed = json_decode($rawJson, true);

        if (!$this->isValidSchema($parsed, $data->numberOfVersions)) {
            $this->throwError('OLLAMA_INVALID_RESPONSE', 'AI trả về kết quả chưa đúng định dạng. Hãy thử tạo lại nội dung.', 502);
        }
        
        $inputTokens = $result['prompt_eval_count'] ?? null;
        $outputTokens = $result['eval_count'] ?? null;
        $totalTokens = null;
        if ($inputTokens !== null && $outputTokens !== null) {
            $totalTokens = $inputTokens + $outputTokens;
        }
        
        // Use total_duration from response if available (it is in nanoseconds)
        if (isset($result['total_duration'])) {
            $durationMs = (int) ($result['total_duration'] / 1000000);
        }

        return new ContentGenerationResult(
            versions: $parsed['versions'],
            metadata: [
                'model' => $model,
                'provider' => 'ollama',
                'generated_at' => Carbon::now()->toIso8601String(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'duration_ms' => $durationMs
            ]
        );
    }

    private function cleanMarkdownJson(string $raw): string
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '```json')) {
            $raw = substr($raw, 7);
        } elseif (str_starts_with($raw, '```')) {
            $raw = substr($raw, 3);
        }
        if (str_ends_with($raw, '```')) {
            $raw = substr($raw, 0, -3);
        }
        return trim($raw);
    }

    private function isValidSchema($parsed, $expectedVersions): bool
    {
        if (!$parsed || !is_array($parsed) || !isset($parsed['versions']) || !is_array($parsed['versions'])) {
            return false;
        }

        if (count($parsed['versions']) !== $expectedVersions) {
            return false;
        }

        foreach ($parsed['versions'] as $version) {
            if (empty($version['title']) || empty($version['content']) || empty($version['cta']) || !isset($version['hashtags']) || !is_array($version['hashtags'])) {
                return false;
            }
        }

        return true;
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
