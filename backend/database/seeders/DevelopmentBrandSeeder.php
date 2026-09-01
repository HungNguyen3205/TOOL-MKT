<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;
use App\Models\ContentTemplate;

class DevelopmentBrandSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return; // Safety guard
        }

        $brand = Brand::create([
            'name' => 'Omachi',
            'slug' => 'omachi',
            'industry' => 'Thực phẩm – mì ăn liền',
            'target_audience' => 'Sinh viên và nhân viên văn phòng 18–35 tuổi',
            'tone' => 'friendly',
            'slogan' => 'Rất ngon mà không sợ nóng',
            'default_cta' => 'Khám phá sản phẩm Omachi phù hợp với bạn ngay hôm nay!',
            'default_hashtags' => ['#Omachi', '#MiKhoaiTay'],
            'writing_rules' => [
                'Không tự tạo giá',
                'Không tự tạo chương trình khuyến mãi',
                'Không so sánh trực tiếp với đối thủ'
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        ContentTemplate::create([
            'brand_id' => $brand->id,
            'name' => 'Bài bán hàng',
            'objective' => 'sales',
            'body_structure' => [
                'Câu mở đầu thu hút sự chú ý',
                'Nêu vấn đề đói bụng/thèm ăn của khách hàng',
                'Giới thiệu giải pháp Omachi',
                'Kêu gọi hành động mua hàng'
            ],
            'is_default' => true,
            'is_active' => true,
        ]);
        
        ContentTemplate::create([
            'brand_id' => $brand->id,
            'name' => 'Giới thiệu tính năng',
            'objective' => 'introduction',
            'body_structure' => [
                'Giới thiệu tính năng/sợi khoai tây',
                'Lợi ích mang lại (không sợ nóng)',
                'Kêu gọi dùng thử'
            ],
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
