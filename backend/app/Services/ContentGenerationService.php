<?php

namespace App\Services;

use App\Contracts\ContentAiProviderInterface;
use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;
use App\Providers\AiProvider\OllamaContentProvider;
use App\Providers\AiProvider\OpenAiContentProvider;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use App\Models\Brand;
use App\Models\ContentTemplate;
use App\Models\ContentGenerationLog;
use Carbon\Carbon;

class ContentGenerationService
{
    protected ContentPromptBuilder $promptBuilder;
    protected ContentQualityValidator $qualityValidator;

    public function __construct(ContentPromptBuilder $promptBuilder, ContentQualityValidator $qualityValidator)
    {
        $this->promptBuilder = $promptBuilder;
        $this->qualityValidator = $qualityValidator;
    }

    public function generate(array $data): array
    {
        $brand = null;
        $template = null;

        if (!empty($data['brand_id'])) {
            $brand = Brand::where('is_active', true)->find($data['brand_id']);
            if (!$brand) {
                $this->throwError('BRAND_INACTIVE', 'Thương hiệu không tồn tại hoặc đã bị vô hiệu hóa.');
            }
        }

        if (!empty($data['content_template_id'])) {
            if (!$brand) {
                $this->throwError('INVALID_BRAND_SELECTION', 'Phải chọn thương hiệu khi sử dụng mẫu.');
            }

            $template = ContentTemplate::where('is_active', true)->find($data['content_template_id']);
            if (!$template || $template->brand_id !== $brand->id) {
                $this->throwError('TEMPLATE_BRAND_MISMATCH', 'Mẫu nội dung không hợp lệ hoặc không thuộc thương hiệu này.');
            }

            if ($template->objective !== $data['objective']) {
                 $this->throwError('TEMPLATE_OBJECTIVE_MISMATCH', 'Mẫu nội dung không khớp với mục tiêu đã chọn.');
            }
        }

        $dto = ContentGenerationData::fromArray($data, $brand, $template);

        $providerName = config('services.ai.provider', 'openai');
        $fallbackEnabled = filter_var(config('services.ai.fallback_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $primaryProvider = $this->getProviderInstance($providerName);

        try {
            return $this->processGeneration($primaryProvider, $providerName, $dto, $brand, $template, $data);
        } catch (HttpResponseException $e) {
            $responseContent = json_decode($e->getResponse()->getContent(), true);
            $errorCode = $responseContent['error_code'] ?? 'UNKNOWN_ERROR';
            
            // Log failure for primary
            $this->logGenerationFailed($providerName, $dto, $brand, $template, $errorCode, $data);

            // Check if fallback is enabled and error is recoverable by fallback (not a validation error like api key missing)
            $nonFallbackErrors = ['OPENAI_API_KEY_MISSING', 'OPENAI_AUTHENTICATION_FAILED'];
            if ($fallbackEnabled && $providerName !== 'ollama' && !in_array($errorCode, $nonFallbackErrors)) {
                Log::warning("Primary AI Provider ({$providerName}) failed. Falling back to Ollama.", [
                    'error' => $responseContent
                ]);

                try {
                    $fallbackProvider = new OllamaContentProvider();
                    return $this->processGeneration($fallbackProvider, 'ollama', $dto, $brand, $template, $data);
                } catch (HttpResponseException $fallbackEx) {
                    Log::error("Fallback Provider (ollama) also failed.");
                    $fallbackResponse = json_decode($fallbackEx->getResponse()->getContent(), true);
                    $this->logGenerationFailed('ollama', $dto, $brand, $template, $fallbackResponse['error_code'] ?? 'UNKNOWN_ERROR', $data);
                    throw $fallbackEx;
                }
            }

            // Rethrow original
            throw $e;
        }
    }

    private function processGeneration(ContentAiProviderInterface $provider, string $providerName, ContentGenerationData $dto, ?Brand $brand, ?ContentTemplate $template, array $requestData): array
    {
        $promptData = $this->promptBuilder->build($dto);
        $startTime = microtime(true);

        try {
            $result = $provider->generate($dto, $promptData);
            $validatedVersions = $this->qualityValidator->validateVersions($result->versions, $dto);

            // Check if we need to retry
            $retryErrors = $this->extractSevereErrors($validatedVersions);
            $retried = false;

            if (count($retryErrors) > 0) {
                // Do a retry
                Log::warning("AI Provider {$providerName} returned poor quality content. Retrying...", ['errors' => $retryErrors]);
                try {
                    $retryResult = $provider->generate($dto, $promptData, true, $retryErrors);
                    // Merge token usage if possible
                    $totalInput = ($result->metadata['input_tokens'] ?? 0) + ($retryResult->metadata['input_tokens'] ?? 0);
                    $totalOutput = ($result->metadata['output_tokens'] ?? 0) + ($retryResult->metadata['output_tokens'] ?? 0);
                    $totalTokens = ($result->metadata['total_tokens'] ?? 0) + ($retryResult->metadata['total_tokens'] ?? 0);
                    
                    $retryResult->metadata['input_tokens'] = $totalInput ?: null;
                    $retryResult->metadata['output_tokens'] = $totalOutput ?: null;
                    $retryResult->metadata['total_tokens'] = $totalTokens ?: null;

                    $validatedVersions = $this->qualityValidator->validateVersions($retryResult->versions, $dto);
                    $result = $retryResult;
                    $retried = true;
                } catch (\Exception $retryEx) {
                    Log::error("Retry failed for {$providerName}. Falling back to first attempt.");
                    // If retry fails, we just keep the first result.
                }
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            // Format result and save log
            $finalResult = $this->formatResult($validatedVersions, $result, $brand, $template, $promptData, $retried, $durationMs);
            $this->logGenerationSuccess($providerName, $dto, $brand, $template, $promptData, $finalResult, $requestData);

            return $finalResult;

        } catch (HttpResponseException $e) {
            throw $e; // Caught by parent
        } catch (\Exception $e) {
            Log::error("Content Generation Exception: " . $e->getMessage());
            $this->throwError('CONTENT_GENERATION_FAILED', 'Lỗi không xác định khi tạo nội dung.');
        }
    }

    private function extractSevereErrors(array $validatedVersions): array
    {
        $errors = [];
        $hasMissingKeywords = false;
        $hasProhibited = false;
        $hasSimilarity = false;

        foreach ($validatedVersions as $v) {
            if ($v['quality']['status'] === 'failed') {
                if (!empty($v['quality']['missing_keywords'])) {
                    $hasMissingKeywords = true;
                }
                if (!empty($v['quality']['prohibited_terms_found'])) {
                    $hasProhibited = true;
                }
            }
            if ($v['quality']['similarity_warning']) {
                $hasSimilarity = true;
            }
        }

        if ($hasMissingKeywords) $errors[] = 'Thiếu từ khóa bắt buộc.';
        if ($hasProhibited) $errors[] = 'Sử dụng từ khóa bị cấm.';
        if ($hasSimilarity) $errors[] = 'Các phiên bản viết quá giống nhau.';

        return $errors;
    }

    private function formatResult(array $versions, ContentGenerationResult $result, ?Brand $brand, ?ContentTemplate $template, array $promptData, bool $retried, int $durationMs): array
    {
        $metadata = $result->metadata;
        $metadata['prompt_version'] = $promptData['version'];
        $metadata['retried'] = $retried;
        
        if (!isset($metadata['duration_ms'])) {
            $metadata['duration_ms'] = $durationMs;
        }

        $estimatedCost = $this->calculateCost($metadata['provider'], $metadata['model'], $metadata['input_tokens'] ?? null, $metadata['output_tokens'] ?? null);
        $metadata['estimated_cost'] = $estimatedCost;
        $metadata['currency'] = 'USD';
        
        if ($brand) {
            $metadata['brand'] = [
                'id' => $brand->id,
                'name' => $brand->name
            ];
        }

        if ($template) {
            $metadata['template'] = [
                'id' => $template->id,
                'name' => $template->name
            ];
        }

        return [
            'success' => true,
            'message' => 'Tạo nội dung thành công.',
            'data' => [
                'versions' => $versions,
                'metadata' => $metadata
            ]
        ];
    }

    private function calculateCost(string $provider, string $model, ?int $inputTokens, ?int $outputTokens): ?float
    {
        if ($provider !== 'openai' || $inputTokens === null || $outputTokens === null) {
            return null;
        }

        $pricing = config("services.openai.models_pricing.{$model}");
        if (!$pricing) {
            return null;
        }

        $cost = (($inputTokens / 1000) * $pricing['input']) + (($outputTokens / 1000) * $pricing['output']);
        return round($cost, 6);
    }

    private function logGenerationSuccess(string $providerName, ContentGenerationData $dto, ?Brand $brand, ?ContentTemplate $template, array $promptData, array $finalResult, array $requestData): void
    {
        try {
            $metadata = $finalResult['data']['metadata'];
            $versions = $finalResult['data']['versions'];
            
            $totalScore = 0;
            foreach ($versions as $v) {
                $totalScore += $v['quality']['score'];
            }
            $avgScore = count($versions) > 0 ? ($totalScore / count($versions)) : 0;

            $logData = filter_var(config('services.ai.log_request_data', true), FILTER_VALIDATE_BOOLEAN) ? $requestData : null;

            ContentGenerationLog::create([
                'brand_id' => $brand?->id,
                'content_template_id' => $template?->id,
                'provider' => $providerName,
                'model' => $metadata['model'] ?? null,
                'prompt_version' => $promptData['version'],
                'prompt_hash' => $promptData['hash'],
                'request_data' => $logData,
                'number_of_versions' => count($versions),
                'input_tokens' => $metadata['input_tokens'] ?? null,
                'output_tokens' => $metadata['output_tokens'] ?? null,
                'total_tokens' => $metadata['total_tokens'] ?? null,
                'estimated_cost' => $metadata['estimated_cost'] ?? null,
                'currency' => $metadata['currency'] ?? 'USD',
                'duration_ms' => $metadata['duration_ms'] ?? null,
                'quality_score_average' => $avgScore,
                'successful' => true,
                'retried' => $metadata['retried'] ?? false
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to save ContentGenerationLog (Success): " . $e->getMessage());
        }
    }

    private function logGenerationFailed(string $providerName, ContentGenerationData $dto, ?Brand $brand, ?ContentTemplate $template, string $errorCode, array $requestData): void
    {
        try {
            $logData = filter_var(config('services.ai.log_request_data', true), FILTER_VALIDATE_BOOLEAN) ? $requestData : null;
            
            ContentGenerationLog::create([
                'brand_id' => $brand?->id,
                'content_template_id' => $template?->id,
                'provider' => $providerName,
                'number_of_versions' => $dto->numberOfVersions,
                'request_data' => $logData,
                'successful' => false,
                'error_code' => $errorCode
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to save ContentGenerationLog (Failed): " . $e->getMessage());
        }
    }

    private function getProviderInstance(string $name): ContentAiProviderInterface
    {
        return match (strtolower($name)) {
            'openai' => new OpenAiContentProvider(),
            'ollama' => new OllamaContentProvider(),
            default => new OpenAiContentProvider(),
        };
    }

    private function throwError($code, $message, $status = 400)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $code
        ], $status));
    }
}
