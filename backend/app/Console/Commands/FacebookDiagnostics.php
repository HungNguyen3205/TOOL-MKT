<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FacebookDiagnostics extends Command
{
    protected $signature = 'app:facebook-diagnostics';
    protected $description = 'Kiểm tra cấu hình tích hợp Meta Graph API (An toàn, không lộ Secret)';

    public function handle()
    {
        $this->info('--- BẮT ĐẦU KIỂM TRA CẤU HÌNH FACEBOOK ---');

        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');
        $redirectUri = config('services.facebook.redirect');
        $frontendRedirect = config('services.facebook.frontend_redirect');
        $graphVersion = config('services.facebook.graph_version', 'v20.0');

        $this->line("1. App ID: " . ($appId ?: 'Chưa cấu hình (Thiếu)'));
        $this->line("2. App Secret: " . ($appSecret ? 'Đã cấu hình (An toàn - Đã bị ẩn)' : 'Chưa cấu hình (Thiếu)'));
        $this->line("3. Redirect URI (Backend): " . ($redirectUri ?: 'Chưa cấu hình (Thiếu)'));
        $this->line("4. Redirect URL (Frontend): " . ($frontendRedirect ?: 'Chưa cấu hình (Thiếu)'));
        $this->line("5. Graph API Version: " . $graphVersion);

        if (!$appId || !$appSecret || !$redirectUri) {
            $this->error('❌ BẠN CHƯA CẤU HÌNH ĐẦY ĐỦ CÁC THÔNG SỐ FACEBOOK TRONG FILE .env');
            $this->line('Vui lòng vào file .env cập nhật FACEBOOK_APP_ID, FACEBOOK_APP_SECRET và FACEBOOK_REDIRECT_URI');
            return 1;
        }

        $this->info('✅ Cấu hình cơ bản đã đầy đủ!');
        return 0;
    }
}
