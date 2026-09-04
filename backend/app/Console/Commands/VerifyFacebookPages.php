<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FacebookPage;
use App\Services\FacebookGraphService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class VerifyFacebookPages extends Command
{
    protected $signature = 'facebook:verify-pages';
    protected $description = 'Kiểm tra token và quyền của các trang Facebook đang hoạt động';

    public function handle(FacebookGraphService $service)
    {
        $this->info('Bắt đầu kiểm tra Facebook Pages...');
        
        $pages = FacebookPage::where('is_active', true)->get();
        $requiredPermissions = ['CREATE_CONTENT', 'MANAGE']; // Typical required tasks

        foreach ($pages as $page) {
            $this->info("Đang kiểm tra: {$page->page_name} ({$page->page_id})");
            
            try {
                $response = $service->verifyPageToken($page->page_id, $page->access_token);
                
                // You can add checking for missing scopes based on the tasks.
                // Currently Facebook returns tasks like CREATE_CONTENT
                $tasks = $page->granted_scopes ?? [];
                $missingPermissions = array_diff($requiredPermissions, $tasks);
                
                if (empty($missingPermissions)) {
                    $page->update([
                        'connection_status' => 'connected',
                        'last_verified_at' => Carbon::now(),
                        'last_error_code' => null,
                        'last_error_message' => null,
                    ]);
                    $this->info("✓ OK: {$page->page_name}");
                } else {
                    $page->update([
                        'connection_status' => 'permission_missing',
                        'last_verified_at' => Carbon::now(),
                        'last_error_code' => 'FACEBOOK_PERMISSION_MISSING',
                        'last_error_message' => 'Thiếu quyền: ' . implode(', ', $missingPermissions),
                    ]);
                    $this->warn("! Thiếu quyền: {$page->page_name}");
                }

            } catch (Exception $e) {
                // If it fails, token is probably invalid
                $page->update([
                    'connection_status' => 'token_expired',
                    'last_verified_at' => Carbon::now(),
                    'last_error_code' => 'FACEBOOK_TOKEN_INVALID',
                    'last_error_message' => $e->getMessage(),
                ]);
                
                // Do not log token
                Log::warning("Facebook page token invalid", [
                    'page_id' => $page->page_id, 
                    'error' => $e->getMessage()
                ]);
                $this->error("x Lỗi: {$page->page_name} - " . $e->getMessage());
            }
        }
        
        $this->info('Hoàn tất kiểm tra.');
        return 0;
    }
}
