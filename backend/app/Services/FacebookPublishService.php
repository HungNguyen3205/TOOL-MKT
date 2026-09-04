<?php

namespace App\Services;

use App\Contracts\SocialPublisherInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class FacebookPublishService implements SocialPublisherInterface
{
    protected $graphService;

    public function __construct(FacebookGraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    public function publishText(
        string $accountId,
        string $accessToken,
        string $message,
        string $idempotencyKey
    ): array {
        try {
            // Note: Idempotency is usually handled by our own queue and DB constraints, 
            // but we can pass it if Graph API supports it for feed posting (optional).
            // Currently, Facebook doesn't natively support idempotency keys for standard feed posts 
            // in a standard way like Stripe does, but it's good to pass it if needed.
            // For now, we will rely on our database idempotency.
            
            $response = $this->graphService->publishTextPost($accountId, $accessToken, $message);
            
            return [
                'success' => true,
                'external_post_id' => $response['id'] ?? null,
                'response_data' => $response
            ];
        } catch (Exception $e) {
            $this->logError("Failed to publish text post to Facebook for page: {$accountId}", $e);
            
            // Re-throw exception so the Job can catch it and mark attempt as failed
            throw $e;
        }
    }

    /**
     * Map platform-specific exceptions to our internal error codes
     */
    public function mapErrorCode(Exception $e): string
    {
        $message = $e->getMessage();

        if (str_contains(strtolower($message), 'timeout')) {
            return 'FACEBOOK_REQUEST_TIMEOUT';
        }

        if (str_contains(strtolower($message), 'rate limit')) {
            return 'FACEBOOK_RATE_LIMITED';
        }
        
        if (str_contains(strtolower($message), 'permission')) {
            return 'FACEBOOK_PERMISSION_MISSING';
        }

        if (str_contains(strtolower($message), 'invalid token') || str_contains(strtolower($message), 'session has expired')) {
            return 'FACEBOOK_TOKEN_INVALID';
        }

        return 'FACEBOOK_PUBLISH_FAILED';
    }
    
    /**
     * Determine if the error is retryable based on the mapped error code
     */
    public function isRetryableError(string $errorCode): bool
    {
        $retryableCodes = [
            'FACEBOOK_REQUEST_TIMEOUT',
            'FACEBOOK_RATE_LIMITED',
            'FACEBOOK_PUBLISH_FAILED', // Generic fails might be temporary network issues
        ];

        return in_array($errorCode, $retryableCodes);
    }

    protected function logError(string $context, Exception $e)
    {
        // Don't log full traces in production for security, just message and code
        Log::error($context, [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
        ]);
    }
}
