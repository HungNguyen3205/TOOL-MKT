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
        
        if (is_null($pages)) {
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
        
        if (is_null($pages)) {
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
        $fbPage = FacebookPage::withTrashed()
    ->firstOrNew([
        'page_id' => $selectedPage['id']
    ]);

    // Khôi phục nếu Page này từng bị ngắt kết nối/xóa mềm
    if ($fbPage->exists && $fbPage->trashed()) {
        $fbPage->restore();
    }

    $fbPage->fill([
        'page_name' => $selectedPage['name'],
        'page_username' => $selectedPage['username'] ?? null,
        'page_picture_url' =>
            $selectedPage['picture']['data']['url'] ?? null,
        'access_token' => $selectedPage['access_token'],
        'granted_scopes' => $selectedPage['tasks'] ?? [],
        'permissions_checked_at' => Carbon::now(),
        'is_active' => true,
        'connection_status' => 'connected',
        'last_verified_at' => Carbon::now(),
        'last_error_code' => null,
        'last_error_message' => null,
    ]);

    $fbPage->save();

    return response()->json([
        'success' => true,
        'message' => 'Kết nối Facebook Page thành công.',
        'data' => $fbPage->fresh(),
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
        // Model tự giải mã access_token bằng encrypted cast
        $pageToken = $page->access_token;

        // Kiểm tra token bằng cách đọc thông tin Page thật
        $data = $this->facebookService->verifyPageToken(
            $page->page_id,
            $pageToken
        );

        // Đảm bảo token trả về đúng Page đã kết nối
        if (
            empty($data['id']) ||
            (string) $data['id'] !== (string) $page->page_id
        ) {
            throw new \RuntimeException(
                'Access Token không thuộc Facebook Page này.'
            );
        }

        // Tasks chỉ dùng để tham khảo, không dùng để kết luận token hết hạn
        $tasks = $page->granted_scopes ?? [];

        if (is_string($tasks)) {
            $tasks = json_decode($tasks, true) ?: [];
        }

        $hasCreateContent = empty($tasks)
            || in_array('CREATE_CONTENT', $tasks, true);

        $page->update([
            'page_name' => $data['name'] ?? $page->page_name,
            'connection_status' => 'connected',
            'last_verified_at' => Carbon::now(),
            'permissions_checked_at' => Carbon::now(),
            'last_error_code' => $hasCreateContent
                ? null
                : 'FACEBOOK_CREATE_CONTENT_TASK_NOT_FOUND',
            'last_error_message' => $hasCreateContent
                ? null
                : 'Token hợp lệ nhưng chưa tìm thấy task CREATE_CONTENT. Hãy thử đăng bài để kiểm tra quyền pages_manage_posts.',
        ]);

        return response()->json([
            'success' => true,
            'message' => $hasCreateContent
                ? 'Kết nối Facebook Page còn hiệu lực.'
                : 'Token còn hiệu lực nhưng chưa xác nhận được task CREATE_CONTENT.',
            'data' => $page->fresh(),
            'meta' => [
                'page_id_verified' => $data['id'],
                'tasks' => $tasks,
                'can_attempt_publish' => true,
            ],
        ]);
    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
        $page->update([
            'connection_status' => 'error',
            'last_verified_at' => Carbon::now(),
            'last_error_code' => 'FACEBOOK_TOKEN_DECRYPT_FAILED',
            'last_error_message' =>
                'Không giải mã được token. APP_KEY có thể đã bị thay đổi.',
        ]);

        return response()->json([
            'success' => false,
            'message' =>
                'Không giải mã được Page Token. Hãy ngắt kết nối và kết nối lại Page.',
            'error_code' => 'FACEBOOK_TOKEN_DECRYPT_FAILED',
            'data' => $page->fresh(),
        ], 422);
        } catch (\Throwable $e) {
            $page->update([
                'connection_status' => 'error',
                'last_verified_at' => Carbon::now(),
                'last_error_code' => 'FACEBOOK_VERIFY_FAILED',
                'last_error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể xác minh kết nối Facebook Page.',
                'error_code' => 'FACEBOOK_VERIFY_FAILED',
                'debug' => config('app.debug')
                    ? $e->getMessage()
                    : null,
                'data' => $page->fresh(),
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
