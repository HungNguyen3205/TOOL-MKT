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
        // Load settings from DB if available
        try {
            $dbSettings = \App\Models\Setting::pluck('value', 'key');
        } catch (\Exception $e) {
            $dbSettings = collect();
        }
        
        $this->appId = $dbSettings['FACEBOOK_APP_ID'] ?? config('services.facebook.app_id');
        $this->appSecret = $dbSettings['FACEBOOK_APP_SECRET'] ?? config('services.facebook.app_secret');
        $this->redirectUri = $dbSettings['FACEBOOK_REDIRECT_URI'] ?? config('services.facebook.redirect');
        
        $this->graphVersion = config('services.facebook.graph_version', 'v20.0');
        $this->scopes = config('services.facebook.scopes', 'pages_show_list,pages_manage_posts,pages_read_engagement');
        $this->timeout = config('services.facebook.timeout', 30);
        
        $this->baseUrl = "https://graph.facebook.com/{$this->graphVersion}";
    }

    public function getAuthUrl(string $state): string
    {
        if (empty($this->appId) || empty($this->redirectUri)) {
            // Trả về URL giả lập nhảy thẳng tới callback nội bộ để test khi chưa có App ID thực
            return url('/api/facebook/callback') . '?code=dummy_local_code_for_testing&state=' . $state;
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
        if ($code === 'dummy_local_code_for_testing') {
            return [
                'access_token' => 'dummy_user_access_token',
                'token_type' => 'bearer',
                'expires_in' => 5183999
            ];
        }

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
        if ($userAccessToken === 'dummy_user_access_token') {
            return [
                [
                    'id' => '1000100010001',
                    'name' => 'Demo Page 1 (Mock)',
                    'access_token' => 'dummy_page_token_1',
                    'tasks' => ['CREATE_CONTENT', 'MANAGE', 'MODERATE']
                ],
                [
                    'id' => '1000100010002',
                    'name' => 'Demo Page 2 (Mock)',
                    'access_token' => 'dummy_page_token_2',
                    'tasks' => ['CREATE_CONTENT']
                ]
            ];
        }

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

    public function verifyPageToken(
    string $pageId,
    string $pageToken
    ): array {
        if (str_starts_with($pageToken, 'dummy_page_token_')) {
            return [
                'id' => $pageId,
                'name' => 'Demo Page (Mock)'
            ];
        }

        $response = Http::timeout($this->timeout)
            ->get("{$this->baseUrl}/{$pageId}", [
                'access_token' => $pageToken,
                'fields' => 'id,name',
            ]);

        if ($response->failed()) {
            $this->logError(
                "Verify Page Token failed for {$pageId}",
                $response
            );

            $error = $response->json('error') ?? [];

            throw new Exception(
                $error['message'] ?? 'Page token không hợp lệ.',
                (int) ($error['code'] ?? 0)
            );
        }

        return $response->json();
    }

    public function publishTextPost(string $pageId, string $pageToken, string $message): array
    {
        if (str_starts_with($pageToken, 'dummy_page_token_')) {
            // Mock delay
            sleep(2);
            return [
                'id' => 'dummy_post_' . time()
            ];
        }

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

    public function publishPhotoPost(string $pageId, string $pageToken, string $message, string $photoPath): array
    {
        if (!file_exists($photoPath)) {
            throw new Exception("Image file not found: " . $photoPath);
        }

        if (str_starts_with($pageToken, 'dummy_page_token_')) {
            // Mock delay
            sleep(2);
            return [
                'id' => 'dummy_photo_' . time(),
                'post_id' => 'dummy_post_' . time()
            ];
        }

        $response = Http::timeout($this->timeout)
            ->attach('source', file_get_contents($photoPath), basename($photoPath))
            ->post("{$this->baseUrl}/{$pageId}/photos", [
                'access_token' => $pageToken,
                'message' => $message,
            ]);

        if ($response->failed()) {
            $this->logError("Publish Photo Post failed for {$pageId}", $response);
            $error = $response->json('error');
            throw new Exception($error['message'] ?? "Failed to publish photo post to Facebook.");
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
