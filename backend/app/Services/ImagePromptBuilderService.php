<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Brand;

class ImagePromptBuilderService
{
    /**
     * Tự tạo prompt hình ảnh dựa trên nội dung bài viết và gợi ý thiết kế.
     *
     * @param Post $post
     * @return string
     */
    public function build(Post $post): string
    {
        $title = $post->title;
        
        // Tóm tắt ngắn nội dung
        $contentSummary = mb_substr(strip_tags($post->content), 0, 300) . '...';
        
        // Trích xuất ngành nghề từ Brand hoặc Post (mặc định)
        $brand = $post->brand;
        $industry = $brand ? $brand->industry : 'Gym, Yoga, Pilates, Bể bơi hoặc Spa';
        
        // Mặc định nội dung giải pháp DANAVA (nếu không có)
        $customerPain = "Khách hàng gặp khó khăn trong việc quản lý và phát triển kinh doanh.";
        $danavaSolution = "DANAVA Studio cung cấp giải pháp truyền thông, phần mềm và marketing chuyên nghiệp.";
        
        // Thông tin gợi ý thiết kế (nếu có từ file DOCX)
        $designSuggestion = "Chưa có gợi ý cụ thể.";
        $imageFormat = "Vuông hoặc Chữ nhật đứng (4:5).";

        if ($post->design_suggestion) {
            $designSuggestion = $post->design_suggestion;
        }

        if ($post->design_format) {
            $imageFormat = $post->design_format;
        }

        // Lắp ghép template Prompt
        $prompt = <<<EOT
Tạo hình ảnh truyền thông chuyên nghiệp cho DANAVA Studio.

Chủ đề: {$title}

Nội dung cần thể hiện:
{$contentSummary}

Nỗi đau của khách hàng:
{$customerPain}

Giải pháp của DANAVA Studio:
{$danavaSolution}

Yêu cầu từ tài liệu:
{$designSuggestion}

Ngành nghề:
{$industry}

Phong cách:
Công nghệ, hiện đại, cao cấp, chuyên nghiệp, ít chữ và dễ đọc.

Màu sắc:
Xanh navy, xanh dương, trắng và cam theo nhận diện DANAVA.

Định dạng:
{$imageFormat}

Yêu cầu:
- Hình ảnh phải bám sát nội dung bài viết.
- Thể hiện rõ nỗi đau và giải pháp.
- Sử dụng đúng bối cảnh Gym, Yoga, Pilates, Bể bơi hoặc Spa.
- Chừa vùng sạch ở góc trên bên phải để backend chèn logo.
- Không tự vẽ logo DANAVA.
- Không tạo chữ DANAVA giả.
- Không tạo QR code giả.
- Không dùng dữ liệu cá nhân thật.
- Không tạo tính năng phần mềm không tồn tại.
- Hạn chế chữ tiếng Việt trong ảnh để tránh sai chính tả.
EOT;

        return trim($prompt);
    }
}
