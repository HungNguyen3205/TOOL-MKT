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

class ContentGenerationService
{
    public function generate(array $data): array
    {
        $brand = null;
        $template = null;

        if (!empty($data['brand_id'])) {
            $brand = Brand::where('is_active', true)->find($data['brand_id']);
            if (!$brand) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Thương hiệu không tồn tại hoặc đã bị vô hiệu hóa.',
                    'error_code' => 'BRAND_INACTIVE'
                ], 400));
            }
        }

        if (!empty($data['content_template_id'])) {
            if (!$brand) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Phải chọn thương hiệu khi sử dụng mẫu.',
                    'error_code' => 'INVALID_BRAND_SELECTION'
                ], 400));
            }

            $template = ContentTemplate::where('is_active', true)->find($data['content_template_id']);
            if (!$template || $template->brand_id !== $brand->id) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Mẫu nội dung không hợp lệ hoặc không thuộc thương hiệu này.',
                    'error_code' => 'TEMPLATE_BRAND_MISMATCH'
                ], 400));
            }

            if ($template->objective !== $data['objective']) {
                 throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Mẫu nội dung không khớp với mục tiêu đã chọn.',
                    'error_code' => 'TEMPLATE_OBJECTIVE_MISMATCH'
                ], 400));
            }
        }

        $dto = ContentGenerationData::fromArray($data, $brand, $template);

        $providerName = config('services.ai.provider', 'openai');
        $fallbackEnabled = filter_var(config('services.ai.fallback_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $primaryProvider = $this->getProviderInstance($providerName);

        try {
            $result = $primaryProvider->generate($dto);
            return $this->formatResult($result, $brand, $template);
        } catch (HttpResponseException $e) {
            // Check if fallback is enabled and we are not already using ollama
            if ($fallbackEnabled && $providerName !== 'ollama') {
                Log::warning("Primary AI Provider ({$providerName}) failed. Falling back to Ollama.", [
                    'error' => $e->getResponse()->getContent()
                ]);

                try {
                    $fallbackProvider = new OllamaContentProvider();
                    $fallbackResult = $fallbackProvider->generate($dto);
                    return $this->formatResult($fallbackResult, $brand, $template);
                } catch (HttpResponseException $fallbackEx) {
                    Log::error("Fallback Provider (ollama) also failed.");
                    throw $fallbackEx;
                }
            }

            // Fallback disabled or already using ollama, rethrow the original error
            throw $e;
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

    private function formatResult(ContentGenerationResult $result, ?Brand $brand, ?ContentTemplate $template): array
    {
        $metadata = $result->metadata;
        
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
                'versions' => $result->versions,
                'metadata' => $metadata
            ]
        ];
    }
}
