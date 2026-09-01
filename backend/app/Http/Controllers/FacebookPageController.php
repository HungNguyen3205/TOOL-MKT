<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FacebookPage;
use App\Services\FacebookGraphService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FacebookPageController extends Controller
{
    protected $facebookService;

    public function __construct(FacebookGraphService $facebookService)
    {
        $this->facebookService = $facebookService;
    }

    public function availablePages(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing session ID.',
                'error_code' => 'MISSING_SESSION_ID'
            ], 400);
        }

        $pages = Cache::get('fb_available_pages_' . $sessionId);
        
        if (!$pages) {
            return response()->json([
                'success' => false,
                'message' => 'Phiên làm việc đã hết hạn hoặc không hợp lệ.',
                'error_code' => 'SESSION_EXPIRED'
            ], 404);
        }

        // Return pages without access token
        $safePages = array_map(function($page) {
            return [
                'id' => $page['id'],
                'name' => $page['name'],
                'username' => $page['username'] ?? null,
                'picture_url' => $page['picture']['data']['url'] ?? null,
                'tasks' => $page['tasks'] ?? []
            ];
        }, $pages);

        return response()->json([
            'success' => true,
            'data' => $safePages
        ]);
    }

    public function connect(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'page_id' => 'required|string',
        ]);

        $sessionId = $request->session_id;
        $pageId = $request->page_id;

        $pages = Cache::get('fb_available_pages_' . $sessionId);
        
        if (!$pages) {
            return response()->json([
                'success' => false,
                'message' => 'Phiên làm việc đã hết hạn. Vui lòng kết nối lại.',
                'error_code' => 'SESSION_EXPIRED'
            ], 400);
        }

        // Find the specific page
        $selectedPage = collect($pages)->firstWhere('id', $pageId);

        if (!$selectedPage) {
            return response()->json([
                'success' => false,
                'message' => 'Page không hợp lệ hoặc không thuộc quyền quản lý.',
                'error_code' => 'PAGE_NOT_FOUND_IN_SESSION'
            ], 400);
        }

        if (empty($selectedPage['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Access Token của Page.',
                'error_code' => 'PAGE_TOKEN_MISSING'
            ], 400);
        }

        // Save or update to Database
        $fbPage = FacebookPage::withTrashed()->updateOrCreate(
            ['page_id' => $selectedPage['id']],
            [
                'page_name' => $selectedPage['name'],
                'page_username' => $selectedPage['username'] ?? null,
                'page_picture_url' => $selectedPage['picture']['data']['url'] ?? null,
                'access_token' => $selectedPage['access_token'], // Will be encrypted by model cast
                'granted_scopes' => $selectedPage['tasks'] ?? [],
                'permissions_checked_at' => Carbon::now(),
                'is_active' => true,
                'connection_status' => 'connected',
                'last_verified_at' => Carbon::now(),
                'deleted_at' => null // Restore if it was soft deleted
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Kết nối Facebook Page thành công.',
            'data' => $fbPage
        ]);
    }

    public function index()
    {
        $pages = FacebookPage::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $pages
        ]);
    }

    public function show($id)
    {
        $page = FacebookPage::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $page
        ]);
    }

    public function verify($id)
    {
        $page = FacebookPage::findOrFail($id);

        try {
            $data = $this->facebookService->verifyPageToken($page->page_id, $page->access_token);
            
            $page->update([
                'connection_status' => 'connected',
                'last_verified_at' => Carbon::now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token còn hiệu lực.',
                'data' => $page
            ]);
        } catch (\Exception $e) {
            $page->update([
                'connection_status' => 'error',
                'last_error_code' => 'FACEBOOK_TOKEN_INVALID',
                'last_error_message' => $e->getMessage(),
                'last_verified_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token đã hết hạn hoặc không hợp lệ.',
                'error_code' => 'FACEBOOK_TOKEN_INVALID',
                'data' => $page
            ], 400);
        }
    }

    public function destroy($id)
    {
        $page = FacebookPage::findOrFail($id);
        
        // Soft delete and mark as disconnected
        $page->update([
            'connection_status' => 'disconnected',
            'is_active' => false
        ]);
        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã ngắt kết nối Facebook Page.'
        ]);
    }
}
