<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Brand;
use App\Models\BrandContentExample;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateAndImportExamples extends Command
{
    protected $signature = 'brand:generate-examples {brand_id}';
    protected $description = 'Generate and import 50 content examples for a brand using Gemini API';

    public function handle()
    {
        $brandId = $this->argument('brand_id');
        $brand = Brand::find($brandId);
        
        if (!$brand) {
            $this->error("Brand with ID {$brandId} not found.");
            return;
        }

        $ollamaUrl = env('OLLAMA_BASE_URL', 'http://localhost:11434') . '/api/generate';
        $ollamaModel = env('OLLAMA_MODEL', 'qwen2.5:3b');

        $items = [
            ['title' => 'Dữ liệu phòng tập đang nằm ở quá nhiều nơi', 'topic' => 'Dữ liệu phân tán', 'objective' => 'Nhận diện nỗi đau', 'hint' => 'Hội viên ở Excel, lịch lớp ở Zalo, doanh thu lại nằm trong sổ quầy?'],
            ['title' => 'Excel không sai, nhưng phòng tập đã lớn hơn Excel', 'topic' => 'Quản lý thủ công', 'objective' => 'Giáo dục thị trường', 'hint' => 'Excel vẫn hữu ích, nhưng không còn đủ khi phòng tập bắt đầu tăng trưởng.'],
            ['title' => 'Năm dấu hiệu phòng tập cần chuyển đổi số', 'topic' => 'Chuyển đổi số', 'objective' => 'Tạo nhu cầu', 'hint' => 'Định dạng: Carousel 5 trang'],
            ['title' => 'Một nền tảng cho toàn bộ hoạt động phòng tập', 'topic' => 'Tổng quan giải pháp', 'objective' => 'Nhận diện sản phẩm', 'hint' => 'Một nền tảng – nhiều nghiệp vụ phòng tập được kết nối.'],
            ['title' => 'Mỗi hội viên cần một hồ sơ đầy đủ', 'topic' => 'Quản lý hội viên', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Đừng để hội viên hết hạn trong im lặng', 'topic' => 'Nhắc gia hạn', 'objective' => 'Thu lead', 'hint' => 'Nhiều hội viên rời đi chỉ vì không được nhắc đúng lúc.'],
            ['title' => 'Gia hạn hội viên đúng thời điểm', 'topic' => 'Chăm sóc hội viên', 'objective' => 'Chuyển đổi', 'hint' => 'CTA gợi ý: Inbox "GIA HẠN".'],
            ['title' => 'Bảo lưu gói tập rõ ràng và có lịch sử', 'topic' => 'Bảo lưu', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Nâng cấp gói tập không còn xử lý thủ công', 'topic' => 'Nâng cấp gói', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Chuyển nhượng gói tập minh bạch', 'topic' => 'Chuyển nhượng', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Quản lý hợp đồng hội viên tập trung', 'topic' => 'Hợp đồng', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Phiếu thu phải gắn đúng hội viên', 'topic' => 'Phiếu thu', 'objective' => 'Giáo dục vận hành', 'hint' => ''],
            ['title' => 'Doanh thu không giống tiền thực thu', 'topic' => 'Báo cáo tài chính', 'objective' => 'Giáo dục thị trường', 'hint' => 'Bán gói 3 triệu không có nghĩa đã thu đủ 3 triệu.'],
            ['title' => 'Chủ phòng tập cần xem tiền thật đã về', 'topic' => 'Tiền thực thu', 'objective' => 'Giới thiệu báo cáo', 'hint' => ''],
            ['title' => 'Công nợ hội viên đang bị bỏ quên?', 'topic' => 'Công nợ', 'objective' => 'Nhận diện nỗi đau', 'hint' => ''],
            ['title' => 'Doanh thu được ghi nhận khi mua hoặc gia hạn', 'topic' => 'Logic doanh thu', 'objective' => 'Hướng dẫn', 'hint' => ''],
            ['title' => 'Báo cáo hôm nay, hôm qua, tuần và tháng', 'topic' => 'Dashboard', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Biết nhân viên nào tạo giao dịch', 'topic' => 'Nhân viên', 'objective' => 'Giới thiệu tính minh bạch', 'hint' => ''],
            ['title' => 'Theo dõi hình thức thanh toán', 'topic' => 'Thanh toán', 'objective' => 'Giới thiệu báo cáo', 'hint' => ''],
            ['title' => 'Xuất báo cáo PDF và Excel', 'topic' => 'Báo cáo', 'objective' => 'Giới thiệu tiện ích', 'hint' => 'Chỉ viết như tính năng chính thức nếu code thực tế đã hoàn thành.'],
            ['title' => 'Lịch lớp không nên nằm trong hàng trăm tin nhắn', 'topic' => 'Quản lý lịch', 'objective' => 'Nhận diện nỗi đau', 'hint' => ''],
            ['title' => 'Phân công huấn luyện viên rõ ràng', 'topic' => 'Huấn luyện viên', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Hội viên chủ động xem lịch và đặt chỗ', 'topic' => 'Booking', 'objective' => 'Giới thiệu trải nghiệm hội viên', 'hint' => ''],
            ['title' => 'Kiểm soát số lượng người trong từng lớp', 'topic' => 'Sức chứa lớp học', 'objective' => 'Giáo dục vận hành', 'hint' => ''],
            ['title' => 'Điểm danh lớp học thuận tiện', 'topic' => 'Điểm danh', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Check-in QR giúp giảm thao tác tại quầy', 'topic' => 'Check-in QR', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Một màn hình check-in đủ thông tin', 'topic' => 'Trải nghiệm check-in', 'objective' => 'Giới thiệu giao diện', 'hint' => ''],
            ['title' => 'Nhìn thấy số ngày còn lại khi check-out', 'topic' => 'Check-out', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Lịch sử check-in nói gì về hội viên?', 'topic' => 'Dữ liệu tập luyện', 'objective' => 'Giáo dục khách hàng', 'hint' => ''],
            ['title' => 'Phát hiện hội viên lâu ngày chưa quay lại', 'topic' => 'Giữ chân hội viên', 'objective' => 'Thu lead', 'hint' => 'CTA gợi ý: Inbox "GIỮ CHÂN".'],
            ['title' => 'App hội viên giúp giảm câu hỏi tại quầy', 'topic' => 'App hội viên', 'objective' => 'Giới thiệu lợi ích', 'hint' => ''],
            ['title' => 'Hội viên tự xem gói tập và thời hạn', 'topic' => 'App hội viên', 'objective' => 'Trải nghiệm khách hàng', 'hint' => ''],
            ['title' => 'Hội viên chủ động đặt lịch trên điện thoại', 'topic' => 'App hội viên', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Xây dựng app mang thương hiệu riêng', 'topic' => 'Nhận diện thương hiệu', 'objective' => 'Thu lead', 'hint' => ''],
            ['title' => 'Thanh toán online thuận tiện hơn', 'topic' => 'Thanh toán online', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Tích hợp PayOS trong vận hành phòng tập', 'topic' => 'PayOS', 'objective' => 'Giới thiệu tích hợp', 'hint' => ''],
            ['title' => 'Tích hợp SeePay để hỗ trợ đối soát', 'topic' => 'SeePay', 'objective' => 'Giới thiệu tích hợp', 'hint' => ''],
            ['title' => 'Báo cáo hoạt động qua Telegram', 'topic' => 'Telegram', 'objective' => 'Giới thiệu tự động hóa', 'hint' => ''],
            ['title' => 'Nhắc lịch và gia hạn qua Zalo ZNS', 'topic' => 'Zalo ZNS', 'objective' => 'Giới thiệu tích hợp', 'hint' => ''],
            ['title' => 'Phân quyền nhân viên theo đúng vai trò', 'topic' => 'Phân quyền', 'objective' => 'Giới thiệu tính năng', 'hint' => ''],
            ['title' => 'Mọi thao tác quan trọng cần có lịch sử', 'topic' => 'Nhật ký hoạt động', 'objective' => 'Giáo dục quản trị', 'hint' => ''],
            ['title' => 'Quản lý chuỗi phòng tập tập trung', 'topic' => 'Nhiều chi nhánh', 'objective' => 'Thu lead', 'hint' => ''],
            ['title' => 'Chủ phòng tập vẫn theo dõi được khi không có mặt', 'topic' => 'Quản lý từ xa', 'objective' => 'Truyền thông lợi ích', 'hint' => ''],
            ['title' => 'Phòng tập mới nên số hóa từ đâu?', 'topic' => 'Setup phòng tập', 'objective' => 'Giáo dục khách hàng', 'hint' => ''],
            ['title' => 'Checklist lựa chọn phần mềm quản lý phòng tập', 'topic' => 'Tư vấn mua hàng', 'objective' => 'Xây dựng niềm tin', 'hint' => 'Định dạng: Carousel hoặc infographic'],
            ['title' => 'Phần mềm có khó sử dụng không?', 'topic' => 'Xử lý phản đối', 'objective' => 'Giải đáp lo ngại', 'hint' => ''],
            ['title' => 'Chuyển dữ liệu từ Excel có phức tạp không?', 'topic' => 'Xử lý phản đối', 'objective' => 'Giải đáp lo ngại', 'hint' => 'Không cam kết cách chuyển dữ liệu nếu quy trình chưa được xác nhận.'],
            ['title' => 'Chi phí phần mềm có thật sự đắt?', 'topic' => 'Xử lý phản đối', 'objective' => 'Giáo dục giá trị', 'hint' => 'Không tự đưa giá nếu bảng giá chưa được lưu trong hệ thống.'],
            ['title' => 'DANAVA Studio đồng hành triển khai như thế nào?', 'topic' => 'Dịch vụ hỗ trợ', 'objective' => 'Xây dựng niềm tin', 'hint' => ''],
            ['title' => 'Đăng ký trải nghiệm DANAVA Studio 14 ngày', 'topic' => 'Chuyển đổi', 'objective' => 'Thu lead', 'hint' => 'Đừng chọn phần mềm chỉ bằng lời giới thiệu. Hãy trải nghiệm trên chính quy trình của phòng tập.']
        ];

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($items as $index => $item) {
            $this->info("Processing (" . ($index + 1) . "/50): {$item['title']}");
            
            $existing = BrandContentExample::where('brand_id', $brand->id)
                ->where('title', $item['title'])
                ->first();

            if ($existing && $existing->content && strlen($existing->content) > 50) {
                $this->info(" -> Already exists and has content. Updating...");
                $isUpdate = true;
                // If it exists, we will overwrite it with the newly generated content just to be sure it matches the new strict rules?
                // The prompt says "Nếu tiêu đề đã tồn tại, cập nhật thay vì tạo bản sao."
                // I will generate new content and update it.
            } else {
                $isUpdate = false;
            }

            // Generate content using Ollama
            $content = $this->generateContentWithOllama($ollamaUrl, $ollamaModel, $item);

            if (!$content) {
                $this->error(" -> Failed to generate content via API.");
                $errorCount++;
                continue;
            }

            try {
                DB::beginTransaction();
                if ($existing) {
                    $existing->update([
                        'content' => $content,
                        'objective' => $item['objective'],
                        'explanation' => "Chủ đề: {$item['topic']}. \nGợi ý: {$item['hint']}",
                        'example_type' => 'good'
                    ]);
                    $updatedCount++;
                } else {
                    $brand->contentExamples()->create([
                        'title' => $item['title'],
                        'content' => $content,
                        'objective' => $item['objective'],
                        'explanation' => "Chủ đề: {$item['topic']}. \nGợi ý: {$item['hint']}",
                        'example_type' => 'good',
                        'is_active' => true,
                        'workspace_id' => $brand->workspace_id ?? 1
                    ]);
                    $createdCount++;
                }
                DB::commit();
                $this->info(" -> Saved successfully.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error(" -> DB Error: " . $e->getMessage());
                $errorCount++;
            }

            // Sleep to avoid rate limits
            sleep(1);
        }

        $this->info("Process completed!");
        $this->info("Created: $createdCount");
        $this->info("Updated: $updatedCount");
        $this->info("Errors: $errorCount");
    }

    private function generateContentWithOllama($url, $model, $item)
    {
        $systemPrompt = "Bạn là chuyên gia marketing chuyên viết content Facebook cho phần mềm DANAVA Studio.
Thương hiệu: DANAVA Studio
Sản phẩm: Phần mềm quản lý Gym, Yoga, Pilates, Fitness, Bể bơi và Spa.
Slogan: Số hóa vận hành – Tự động hóa phòng tập – Tăng trưởng hội viên.
Ưu đãi: Trải nghiệm miễn phí 14 ngày.
Website: https://danava.vn/studio | Hotline: 0935 91 7677
Giọng văn: Chuyên nghiệp, thân thiện, hiện đại, thực tế, dễ hiểu. Gọi khách hàng là 'Anh/Chị' hoặc 'chủ phòng tập', xưng 'chúng tôi' hoặc 'DANAVA Studio'.

QUY TẮC VIẾT:
- Dài khoảng 100-220 từ (Reels 50-100 từ). Đoạn văn ngắn, dễ đọc.
- Ưu tiên cấu trúc: Hook -> Nỗi đau -> Hậu quả -> Giải pháp -> Lợi ích -> CTA.
- Tối đa 5 emoji/bài.
- 3-7 hashtag, luôn có #DANAVAStudio.
- Không dùng: tốt nhất, số 1, 100%, bảo mật tuyệt đối. Không cam kết doanh thu/chi phí.
- CTA chuẩn:
🎁 Đăng ký trải nghiệm miễn phí DANAVA Studio trong 14 ngày!
👉 https://danava.vn/studio
📞 Hotline/Zalo: 0935 91 7677

YÊU CẦU: Hãy viết 1 bài viết Facebook hoàn chỉnh dựa trên:
- Tiêu đề quản trị: {$item['title']}
- Chủ đề: {$item['topic']}
- Mục tiêu: {$item['objective']}
- Gợi ý/Hook: {$item['hint']}

CHỈ TRẢ VỀ CHUỖI VĂN BẢN (TEXT) BÀI VIẾT (bao gồm Hook, Nội dung, CTA, Hashtag, Gợi ý hình ảnh). Không dùng định dạng JSON. Không thêm lời mở đầu hay kết thúc.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(120)->post($url, [
                'model' => $model,
                'prompt' => $systemPrompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.7,
                    'num_predict' => 1000,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['response'])) {
                    return trim($data['response']);
                }
            } else {
                Log::error("Ollama API Error: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Ollama API Exception: " . $e->getMessage());
        }

        return null;
    }
}
