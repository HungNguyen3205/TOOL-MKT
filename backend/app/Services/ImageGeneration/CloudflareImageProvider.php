<?php

namespace App\Services\ImageGeneration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareImageProvider implements ImageGenerationProviderInterface
{
    protected $accountId;
    protected $apiToken;
    protected $model;
    protected $timeout;

    public function __construct()
    {
        $this->accountId = config('services.cloudflare_ai.account_id');
        $this->apiToken = config('services.cloudflare_ai.api_token');
        $this->model = config('services.cloudflare_ai.image_model', '@cf/black-forest-labs/flux-1-schnell');
        $this->timeout = config('services.cloudflare_ai.timeout', 120);
    }

    public function generate(array $input): array
    {
        if (!$this->accountId || !$this->apiToken) {
            return $this->errorResponse('CLOUDFLARE_CONFIG_MISSING', 'Cloudflare configuration is missing.');
        }

        $prompt = $input['prompt'] ?? '';
        if (empty($prompt)) {
            return $this->errorResponse('IMAGE_GENERATION_FAILED', 'Prompt is required.');
        }

        $payload = [
            'prompt' => $prompt,
        ];
        
        if (isset($input['num_steps'])) {
            $payload['num_steps'] = $input['num_steps'];
        }

        // Encode the model correctly, preserving @ and / but encoding everything else if needed.
        // Actually, urlencode encodes @ and /, we shouldn't encode them for Cloudflare. 
        // Cloudflare endpoint expects @cf/... 
        $encodedModel = str_replace(['%40', '%2F'], ['@', '/'], urlencode($this->model));
        
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$encodedModel}";

        try {
            $response = Http::withToken($this->apiToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->status() === 401 || $response->status() === 403) {
                return $this->errorResponse('CLOUDFLARE_AUTHENTICATION_FAILED', 'Invalid Cloudflare API token or permissions.');
            }

            if ($response->status() === 429) {
                return $this->errorResponse('CLOUDFLARE_RATE_LIMITED', 'Rate limited by Cloudflare AI.');
            }

            if ($response->status() === 404) {
                return $this->errorResponse('CLOUDFLARE_MODEL_NOT_FOUND', 'Cloudflare AI Model not found.');
            }

            if ($response->failed()) {
                Log::error("Cloudflare AI Error: " . $response->body());
                return $this->errorResponse('CLOUDFLARE_INVALID_RESPONSE', 'Failed to generate image: HTTP ' . $response->status());
            }

            $data = $response->json();

            // Check if response has success true
            if (!isset($data['success']) || $data['success'] !== true) {
                $errorMsg = $data['errors'][0]['message'] ?? 'Unknown Cloudflare Error';
                return $this->errorResponse('CLOUDFLARE_INVALID_RESPONSE', $errorMsg);
            }

            // CF usually returns base64 image string in result.image or result
            $base64Image = null;
            
            if (isset($data['result']['image'])) {
                $base64Image = $data['result']['image'];
            } elseif (isset($data['result']) && is_string($data['result'])) {
                $base64Image = $data['result'];
            }

            if (!$base64Image) {
                return $this->errorResponse('CLOUDFLARE_INVALID_RESPONSE', 'Response does not contain result.image');
            }

            // Remove prefix if exists
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            }

            $decoded = base64_decode($base64Image, true);
            
            if ($decoded === false || empty($decoded)) {
                return $this->errorResponse('IMAGE_DECODE_FAILED', 'Failed to decode base64 image data.');
            }

            return [
                'success' => true,
                'image_data' => $decoded,
                'model' => $this->model,
                'provider' => 'cloudflare',
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->errorResponse('IMAGE_GENERATION_TIMEOUT', 'Connection timed out while waiting for image generation.');
        } catch (\Exception $e) {
            Log::error("Cloudflare Image Generation Exception: " . $e->getMessage());
            return $this->errorResponse('IMAGE_GENERATION_FAILED', 'An unexpected error occurred during image generation.');
        }
    }

    public function verifyConnection(): array
    {
        if (!$this->accountId || !$this->apiToken) {
            return $this->errorResponse('CLOUDFLARE_CONFIG_MISSING', 'Configuration missing.');
        }

        return ['success' => true, 'message' => 'Configuration appears valid.'];
    }

    protected function errorResponse(string $code, string $message): array
    {
        return [
            'success' => false,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }
}
