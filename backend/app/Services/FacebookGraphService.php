<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FacebookGraphService
{
    protected $appId;
    protected $appSecret;
    protected $redirectUri;
    protected $graphVersion;
    protected $scopes;
    protected $timeout;
    protected $baseUrl;

    public function __construct()
    {
        $this->appId = config('services.facebook.app_id');
        $this->appSecret = config('services.facebook.app_secret');
        $this->redirectUri = config('services.facebook.redirect');
        $this->graphVersion = config('services.facebook.graph_version', 'v20.0');
        $this->scopes = config('services.facebook.scopes', 'pages_show_list,pages_manage_posts,pages_read_engagement');
        $this->timeout = config('services.facebook.timeout', 30);
        
        $this->baseUrl = "https://graph.facebook.com/{$this->graphVersion}";
    }

    public function getAuthUrl(string $state): string
    {
        if (empty($this->appId) || empty($this->redirectUri)) {
            throw new Exception("Facebook App ID or Redirect URI is not configured.");
        }

        $query = http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => $this->scopes,
            'response_type' => 'code',
            // 'auth_type' => 'rerequest' // optionally force rerequest if permissions were declined
        ]);

        return "https://www.facebook.com/{$this->graphVersion}/dialog/oauth?{$query}";
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::timeout($this->timeout)->get("{$this->baseUrl}/oauth/access_token", [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'client_secret' => $this->appSecret,
            'code' => $code,
        ]);

        if ($response->failed()) {
            $this->logError('Exchange Code for Token failed', $response);
            throw new Exception("Failed to exchange authorization code for access token.");
        }

        return $response->json();
    }

    public function getUserPages(string $userAccessToken): array
    {
        $response = Http::timeout($this->timeout)->get("{$this->baseUrl}/me/accounts", [
            'access_token' => $userAccessToken,
            'fields' => 'id,name,username,picture{url},access_token,tasks',
        ]);

        if ($response->failed()) {
            $this->logError('Get User Pages failed', $response);
            throw new Exception("Failed to fetch user pages.");
        }

        return $response->json('data') ?? [];
    }

    public function verifyPageToken(string $pageId, string $pageToken): array
    {
        // Try to fetch page details using its token to verify if it's still valid
        $response = Http::timeout($this->timeout)->get("{$this->baseUrl}/{$pageId}", [
            'access_token' => $pageToken,
            'fields' => 'id,name,permissions',
        ]);

        if ($response->failed()) {
            $this->logError("Verify Page Token failed for {$pageId}", $response);
            throw new Exception("Failed to verify page token. It might be expired or revoked.");
        }

        return $response->json();
    }

    public function publishTextPost(string $pageId, string $pageToken, string $message): array
    {
        $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/{$pageId}/feed", [
            'access_token' => $pageToken,
            'message' => $message,
        ]);

        if ($response->failed()) {
            $this->logError("Publish Text Post failed for {$pageId}", $response);
            $error = $response->json('error');
            throw new Exception($error['message'] ?? "Failed to publish post to Facebook.");
        }

        return $response->json();
    }

    protected function logError(string $message, \Illuminate\Http\Client\Response $response)
    {
        // Do not log tokens or secrets
        $errorData = $response->json();
        if (isset($errorData['error'])) {
            // Facebook Graph API standard error format
            Log::error("FacebookGraphService: {$message}", [
                'status' => $response->status(),
                'error' => [
                    'message' => $errorData['error']['message'] ?? 'Unknown',
                    'type' => $errorData['error']['type'] ?? 'Unknown',
                    'code' => $errorData['error']['code'] ?? 'Unknown',
                    'error_subcode' => $errorData['error']['error_subcode'] ?? 'Unknown',
                ]
            ]);
        } else {
            Log::error("FacebookGraphService: {$message}", ['status' => $response->status()]);
        }
    }
}
