<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Xóa các page mock an toàn
        DB::table('facebook_pages')
            ->whereIn('page_id', ['mock_page_1', 'mock_page_2'])
            ->orWhere('access_token', 'like', 'mock_token_%')
            ->delete();

        // Xóa App Secret dạng plaintext trong DB nếu có để bảo mật
        DB::table('settings')
            ->where('key', 'FACEBOOK_APP_SECRET')
            ->delete();
    }

    public function down(): void
    {
        // Cannot cleanly restore mock data without knowing exactly what was there, 
        // but this is an irreversible cleanup migration by design.
    }
};
