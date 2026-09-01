<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FacebookGraphService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class FacebookAuthController extends Controller
{
    protected $facebookService;

    public function __construct(FacebookGraphService $facebookService)
    {
        $this->facebookService = $facebookService;
    }

    public function getAuthUrl()
    {
        try {
            // Generate CSRF state
            $state = Str::random(40);
            
            // Store state in cache for 15 minutes
            Cache::put('fb_oauth_state_' . $state, true, now()->addMinutes(15));

            $url = $this->facebookService->getAuthUrl($state);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo URL đăng nhập Facebook.',
                'error_code' => 'FACEBOOK_AUTH_URL_FAILED',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        $frontendRedirect = config('services.facebook.frontend_redirect');

        if ($request->has('error')) {
            $errorReason = $request->get('error_reason');
            $errorMessage = $request->get('error_description');
            
            return redirect()->away($frontendRedirect . '?status=error&error_code=FACEBOOK_OAUTH_DENIED&message=' . urlencode($errorMessage));
        }

        $code = $request->get('code');
        $state = $request->get('state');

        if (!$code || !$state) {
            return redirect()->away($frontendRedirect . '?status=error&error_code=FACEBOOK_OAUTH_INVALID');
        }

        // Validate state
        if (!Cache::pull('fb_oauth_state_' . $state)) {
            return redirect()->away($frontendRedirect . '?status=error&error_code=FACEBOOK_OAUTH_STATE_INVALID');
        }

        try {
            // Exchange code for token
            $tokenData = $this->facebookService->exchangeCodeForToken($code);
            $userAccessToken = $tokenData['access_token'];

            // Fetch user pages
            $pages = $this->facebookService->getUserPages($userAccessToken);

            // Filter out pages where user doesn't have required tasks (e.g., CREATE_CONTENT)
            // Or just store all pages and let frontend choose.
            // We store the pages securely in cache with a temporary session ID so frontend can fetch them
            $sessionId = Str::random(40);
            Cache::put('fb_available_pages_' . $sessionId, $pages, now()->addMinutes(30));

            return redirect()->away($frontendRedirect . '?status=success&session_id=' . $sessionId);

        } catch (\Exception $e) {
            return redirect()->away($frontendRedirect . '?status=error&error_code=FACEBOOK_CODE_EXCHANGE_FAILED&message=' . urlencode($e->getMessage()));
        }
    }
}
