<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DatabaseHealth extends Command
{
    protected $signature = 'app:database-health';
    protected $description = 'Kiểm tra cấu trúc và tính toàn vẹn dữ liệu trước/sau Sprint 7';

    public function handle()
    {
        $this->info('--- BẮT ĐẦU KIỂM TRA SỨC KHỎE DATABASE ---');

        $tables = [
            'posts',
            'brands',
            'facebook_pages',
            'content_templates',
            'publications',
            'scheduled_publications',
        ];

        $allGood = true;

        foreach ($tables as $table) {
            $this->line("Kiểm tra bảng: {$table}");
            if (!Schema::hasTable($table)) {
                if ($table !== 'scheduled_publications') { // Might not exist depending on Sprint 6 status
                    $this->error("Thiếu bảng: {$table}");
                    $allGood = false;
                } else {
                    $this->warn("Không tìm thấy bảng scheduled_publications (Có thể chưa tạo)");
                }
                continue;
            }

            $count = DB::table($table)->count();
            $this->info(" > Số dòng dữ liệu (records): {$count}");
        }

        $this->line("Kiểm tra các cột quan trọng của bảng posts:");
        $importantColumns = [
            'id', 'brand_id', 'content_template_id', 'title', 'content', 'cta',
            'hashtags', 'status', 'published_at', 'last_facebook_post_id'
        ];

        foreach ($importantColumns as $column) {
            if (Schema::hasColumn('posts', $column)) {
                $this->info(" > Cột {$column} tồn tại.");
            } else {
                $this->error(" > Cột {$column} BỊ MẤT!");
                $allGood = false;
            }
        }

        if ($allGood) {
            $this->info('✅ DATABASE HEALTH OKE: Cấu trúc và dữ liệu cũ không bị phá vỡ.');
        } else {
            $this->error('❌ CÓ VẤN ĐỀ VỀ DATABASE: Vui lòng kiểm tra lại migrations!');
            return 1;
        }

        return 0;
    }
}
