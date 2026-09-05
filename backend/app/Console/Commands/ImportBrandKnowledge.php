<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Brand;
use App\Models\BrandKnowledgeItem;
use Illuminate\Support\Facades\DB;

class ImportBrandKnowledge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brand:import-knowledge {brand_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import 50 knowledge items for a brand';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $brandId = $this->argument('brand_id');
        $brand = Brand::find($brandId);
        
        if (!$brand) {
            $this->error("Brand with ID {$brandId} not found.");
            return;
        }

        $items = [
            ['Tiêu đề' => 'Tổng quan DANAVA Studio', 'Danh mục' => 'Sản phẩm', 'Nội dung' => 'DANAVA Studio là nền tảng quản lý và chuyển đổi số dành cho Gym, Yoga, Pilates, Fitness, Bể bơi và Spa. Hệ thống giúp quản lý tập trung hội viên, gói tập, hợp đồng, lịch lớp, check-in, thanh toán, công nợ, chăm sóc khách hàng và báo cáo vận hành.'],
            ['Tiêu đề' => 'Định vị thương hiệu DANAVA Studio', 'Danh mục' => 'Thương hiệu', 'Nội dung' => 'DANAVA Studio được định vị là nền tảng quản lý phòng tập toàn diện tại Việt Nam, giúp kết nối vận hành, hội viên, thanh toán, chăm sóc khách hàng và marketing trên một hệ thống thống nhất.'],
            ['Tiêu đề' => 'Slogan DANAVA Studio', 'Danh mục' => 'Thương hiệu', 'Nội dung' => 'Slogan chính thức của DANAVA Studio là “Số hóa vận hành – Tự động hóa phòng tập – Tăng trưởng hội viên”.'],
            ['Tiêu đề' => 'Khách hàng mục tiêu', 'Danh mục' => 'Khách hàng', 'Nội dung' => 'Khách hàng mục tiêu gồm chủ đầu tư, chủ phòng tập và người quản lý Gym, Yoga, Pilates, Fitness, Bể bơi, Spa và trung tâm chăm sóc sức khỏe tại Việt Nam.'],
            ['Tiêu đề' => 'Phòng tập phù hợp với DANAVA Studio', 'Danh mục' => 'Khách hàng', 'Nội dung' => 'DANAVA Studio phù hợp với phòng tập mới thành lập, studio quy mô nhỏ, cơ sở đang tăng trưởng và chuỗi có nhiều chi nhánh cần quản lý tập trung.'],
            ['Tiêu đề' => 'Vấn đề dữ liệu phân tán', 'Danh mục' => 'Nỗi đau', 'Nội dung' => 'Nhiều phòng tập lưu hội viên trong Excel, lịch lớp trên Zalo, doanh thu trong sổ quầy và báo cáo ở phần mềm khác. Dữ liệu phân tán làm tăng thời gian xử lý và nguy cơ sai sót.'],
            ['Tiêu đề' => 'Hạn chế của quản lý bằng Excel', 'Danh mục' => 'Nỗi đau', 'Nội dung' => 'Excel phù hợp khi dữ liệu còn ít nhưng khó đáp ứng khi số hội viên, nhân viên, hợp đồng và giao dịch tăng. Việc nhập liệu thủ công dễ gây trùng lặp, bỏ sót và khó kiểm soát lịch sử.'],
            ['Tiêu đề' => 'Quản lý hội viên tập trung', 'Danh mục' => 'Tính năng', 'Nội dung' => 'DANAVA Studio lưu hồ sơ hội viên, thông tin liên hệ, gói tập, hợp đồng, giao dịch, check-in và trạng thái tập luyện trên một hệ thống.'],
            ['Tiêu đề' => 'Hồ sơ hội viên', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Mỗi hội viên có hồ sơ riêng để theo dõi thông tin cá nhân, gói đang sử dụng, thời hạn, công nợ, lịch sử thanh toán và lịch sử tập luyện.'],
            ['Tiêu đề' => 'Quản lý gói tập', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hệ thống hỗ trợ tạo và quản lý các gói tập theo thời hạn, số buổi, dịch vụ và điều kiện sử dụng phù hợp với từng mô hình phòng tập.'],
            ['Tiêu đề' => 'Gia hạn gói tập', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Khi hội viên tiếp tục mua gói, hệ thống ghi nhận giao dịch gia hạn, thời gian áp dụng, doanh thu và lịch sử chăm sóc của hội viên.'],
            ['Tiêu đề' => 'Bảo lưu gói tập', 'Danh mục' => 'Tính năng', 'Nội dung' => 'DANAVA Studio hỗ trợ ghi nhận yêu cầu bảo lưu, thời gian bắt đầu, thời gian kết thúc và lý do bảo lưu theo chính sách của phòng tập.'],
            ['Tiêu đề' => 'Nâng cấp gói tập', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Nhân viên có thể ghi nhận việc hội viên nâng cấp sang gói mới và theo dõi phần giá trị, thời hạn hoặc quyền lợi được điều chỉnh.'],
            ['Tiêu đề' => 'Chuyển nhượng gói tập', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hệ thống hỗ trợ quản lý quy trình chuyển nhượng gói tập giữa các hội viên theo chính sách được phòng tập thiết lập.'],
            ['Tiêu đề' => 'Quản lý hợp đồng', 'Danh mục' => 'Tính năng', 'Nội dung' => 'DANAVA Studio giúp tạo, lưu trữ và tra cứu hợp đồng hội viên, gói đã mua, giá trị hợp đồng, nhân viên phụ trách, tình trạng thanh toán và công nợ.'],
            ['Tiêu đề' => 'Quản lý hóa đơn và phiếu thu', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Mỗi khoản thu được liên kết với hội viên, hợp đồng, gói tập, nhân viên và hình thức thanh toán để thuận tiện kiểm tra và đối soát.'],
            ['Tiêu đề' => 'Quản lý lịch lớp', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hệ thống hỗ trợ tạo lịch lớp, khung giờ, phòng tập, sức chứa và trạng thái hoạt động của từng lớp.'],
            ['Tiêu đề' => 'Quản lý huấn luyện viên', 'Danh mục' => 'Tính năng', 'Nội dung' => 'DANAVA Studio hỗ trợ phân công huấn luyện viên theo lớp, ca làm việc hoặc chương trình tập để quản lý lịch giảng dạy rõ ràng.'],
            ['Tiêu đề' => 'Đặt chỗ lớp học', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hội viên có thể xem lịch và đặt chỗ trước. Phòng tập kiểm soát số lượng người đăng ký, sức chứa và danh sách tham gia.'],
            ['Tiêu đề' => 'Điểm danh lớp học', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Nhân viên hoặc huấn luyện viên có thể ghi nhận tình trạng tham gia lớp, giúp theo dõi lịch sử tập luyện của từng hội viên.'],
            ['Tiêu đề' => 'Check-in bằng mã QR', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hội viên có thể check-in bằng mã QR. Hệ thống hiển thị thông tin hội viên, gói đang sử dụng, trạng thái và thời hạn còn lại.'],
            ['Tiêu đề' => 'Check-in bằng FaceID', 'Danh mục' => 'Tính năng', 'Nội dung' => 'DANAVA Studio có thể hỗ trợ nhận diện khuôn mặt để rút ngắn thao tác check-in và hạn chế việc sử dụng thông tin của người khác.'],
            ['Tiêu đề' => 'Check-out hội viên', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Chức năng check-out ghi nhận thời gian rời phòng tập và hiển thị số ngày còn lại của gói để nhân viên chủ động nhắc hội viên.'],
            ['Tiêu đề' => 'Lịch sử check-in', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Mỗi lượt check-in được lưu vào lịch sử hội viên, giúp phòng tập theo dõi tần suất tập và nhận biết hội viên lâu ngày chưa quay lại.'],
            ['Tiêu đề' => 'Quản lý POS', 'Danh mục' => 'Tính năng', 'Nội dung' => 'DANAVA Studio hỗ trợ quản lý hoạt động bán hàng tại quầy, liên kết sản phẩm, dịch vụ, giao dịch và hội viên trên cùng hệ thống.'],
            ['Tiêu đề' => 'Quản lý ví hội viên', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Ví hội viên giúp lưu và theo dõi số dư hoặc giá trị được sử dụng trong hệ thống theo chính sách do phòng tập thiết lập.'],
            ['Tiêu đề' => 'Tích điểm và ưu đãi', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Phòng tập có thể xây dựng chương trình tích điểm, quyền lợi và ưu đãi để chăm sóc, khuyến khích hội viên quay lại và tăng khả năng gia hạn.'],
            ['Tiêu đề' => 'App dành cho hội viên', 'Danh mục' => 'Tính năng', 'Nội dung' => 'App hội viên giúp khách hàng xem gói tập, thời hạn, lịch lớp, đặt chỗ, lịch sử tập luyện, ví, ưu đãi và các thông tin liên quan.'],
            ['Tiêu đề' => 'App mang thương hiệu riêng', 'Danh mục' => 'Giải pháp', 'Nội dung' => 'DANAVA Studio có thể phát triển app hội viên theo nhận diện của phòng tập, giúp nâng cao tính chuyên nghiệp và trải nghiệm khách hàng.'],
            ['Tiêu đề' => 'Thanh toán online', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hội viên có thể thực hiện thanh toán trực tuyến. Giao dịch được liên kết với đúng hội viên, gói tập, hợp đồng và hóa đơn.'],
            ['Tiêu đề' => 'Tích hợp PayOS', 'Danh mục' => 'Tích hợp', 'Nội dung' => 'DANAVA Studio hỗ trợ tích hợp PayOS để tạo và ghi nhận giao dịch thanh toán online, giúp giảm nhập liệu thủ công và thuận tiện đối soát.'],
            ['Tiêu đề' => 'Tích hợp SeePay', 'Danh mục' => 'Tích hợp', 'Nội dung' => 'DANAVA Studio hỗ trợ tích hợp SeePay để tiếp nhận và đối chiếu thông tin thanh toán theo cấu hình của phòng tập.'],
            ['Tiêu đề' => 'Doanh thu trong DANAVA Studio', 'Danh mục' => 'Báo cáo', 'Nội dung' => 'Doanh thu được ghi nhận tại thời điểm hội viên mua mới hoặc gia hạn gói. Giao dịch phát sinh trong tháng nào được tính vào doanh thu của tháng đó.'],
            ['Tiêu đề' => 'Tiền thực thu', 'Danh mục' => 'Báo cáo', 'Nội dung' => 'Tiền thực thu là số tiền phòng tập đã thực tế nhận được từ khách hàng. Chỉ số này không bao gồm phần công nợ chưa thanh toán.'],
            ['Tiêu đề' => 'Phân biệt doanh thu và tiền thu', 'Danh mục' => 'Báo cáo', 'Nội dung' => 'Doanh thu phản ánh giá trị bán hàng hoặc hợp đồng phát sinh. Tiền thu phản ánh số tiền thực tế đã nhận. Hai chỉ số cần được hiển thị riêng để chủ phòng tập hiểu đúng tình hình tài chính.'],
            ['Tiêu đề' => 'Quản lý công nợ', 'Danh mục' => 'Tính năng', 'Nội dung' => 'Hệ thống theo dõi tổng giá trị hợp đồng, số tiền đã thanh toán và phần còn nợ của từng hội viên để phòng tập chủ động thu hồi công nợ.'],
            ['Tiêu đề' => 'Báo cáo doanh thu', 'Danh mục' => 'Báo cáo', 'Nội dung' => 'Báo cáo doanh thu có thể theo dõi theo ngày, tuần, tháng, hội viên, gói tập, nhân viên và hình thức thanh toán.'],
            ['Tiêu đề' => 'Báo cáo tiền thu', 'Danh mục' => 'Báo cáo', 'Nội dung' => 'Báo cáo tiền thu thể hiện số tiền thực tế đã về phòng tập theo từng khoảng thời gian và không cộng phần công nợ chưa thu.'],
            ['Tiêu đề' => 'Báo cáo thời gian thực', 'Danh mục' => 'Báo cáo', 'Nội dung' => 'Dữ liệu phát sinh trong hệ thống được cập nhật vào báo cáo để chủ phòng tập theo dõi hoạt động vận hành và tài chính kịp thời.'],
            ['Tiêu đề' => 'Báo cáo qua Telegram', 'Danh mục' => 'Tích hợp', 'Nội dung' => 'DANAVA Studio hỗ trợ gửi thông tin hoạt động và báo cáo đến nhóm Telegram theo cấu hình, giúp người quản lý theo dõi từ xa.'],
            ['Tiêu đề' => 'Tích hợp Zalo ZNS', 'Danh mục' => 'Tích hợp', 'Nội dung' => 'Hệ thống có thể kết nối Zalo ZNS để gửi thông báo, OTP, nhắc lịch hoặc nhắc gia hạn theo nội dung và cấu hình đã được phê duyệt.'],
            ['Tiêu đề' => 'Tự động nhắc lịch', 'Danh mục' => 'Tự động hóa', 'Nội dung' => 'DANAVA Studio hỗ trợ nhắc lịch học hoặc lịch tập, giúp giảm tình trạng hội viên quên lịch và giảm công việc nhắn tin thủ công của nhân viên.'],
            ['Tiêu đề' => 'Nhắc hội viên sắp hết hạn', 'Danh mục' => 'Tự động hóa', 'Nội dung' => 'Hệ thống giúp lọc và nhận biết hội viên có gói sắp hết hạn để nhân viên chăm sóc đúng người, đúng thời điểm.'],
            ['Tiêu đề' => 'Chăm sóc hội viên lâu ngày chưa tập', 'Danh mục' => 'Giải pháp', 'Nội dung' => 'Dựa trên lịch sử check-in, phòng tập có thể nhận biết hội viên lâu ngày chưa quay lại để chủ động hỏi thăm và hỗ trợ.'],
            ['Tiêu đề' => 'Phân quyền nhân viên', 'Danh mục' => 'Quản trị', 'Nội dung' => 'DANAVA Studio hỗ trợ phân quyền theo vai trò để mỗi nhân viên chỉ truy cập và thực hiện các chức năng phù hợp với nhiệm vụ.'],
            ['Tiêu đề' => 'Nhật ký hoạt động', 'Danh mục' => 'Quản trị', 'Nội dung' => 'Nhật ký hoạt động ghi nhận các thao tác quan trọng trên hệ thống, hỗ trợ người quản lý kiểm tra lịch sử và xác định người thực hiện.'],
            ['Tiêu đề' => 'Quản lý nhiều chi nhánh', 'Danh mục' => 'Quản trị', 'Nội dung' => 'DANAVA Studio hỗ trợ định hướng quản lý dữ liệu nhiều cơ sở hoặc chi nhánh trên cùng hệ thống, giúp chủ doanh nghiệp theo dõi tập trung.'],
            ['Tiêu đề' => 'AI Marketing', 'Danh mục' => 'Marketing', 'Nội dung' => 'DANAVA Studio kết hợp công cụ AI Marketing để hỗ trợ xây dựng nội dung theo thương hiệu, nhóm khách hàng, nỗi đau, giải pháp và mục tiêu truyền thông.'],
            ['Tiêu đề' => 'Quy trình triển khai và hỗ trợ', 'Danh mục' => 'Dịch vụ', 'Nội dung' => 'DANAVA Studio cung cấp hoạt động tư vấn, thiết lập, hướng dẫn sử dụng và đồng hành trong quá trình triển khai. Phạm vi cụ thể phụ thuộc vào gói dịch vụ đã thống nhất.'],
            ['Tiêu đề' => 'Trải nghiệm miễn phí DANAVA Studio', 'Danh mục' => 'Ưu đãi', 'Nội dung' => 'Khách hàng có thể đăng ký trải nghiệm miễn phí DANAVA Studio trong 14 ngày. Website: https://danava.vn/studio. Hotline/Zalo: 0935 91 7677.']
        ];

        $categoryMap = [
            'Sản phẩm' => 'product',
            'Thương hiệu' => 'brand',
            'Khách hàng' => 'customer',
            'Nỗi đau' => 'pain_point',
            'Tính năng' => 'feature',
            'Giải pháp' => 'solution',
            'Tích hợp' => 'integration',
            'Báo cáo' => 'report',
            'Tự động hóa' => 'automation',
            'Quản trị' => 'management',
            'Marketing' => 'marketing',
            'Dịch vụ' => 'service',
            'Ưu đãi' => 'promotion',
        ];

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $category = $categoryMap[$item['Danh mục']] ?? 'other';
                
                $existing = BrandKnowledgeItem::where('brand_id', $brand->id)
                    ->where('title', $item['Tiêu đề'])
                    ->first();
                
                if ($existing) {
                    if ($existing->content === $item['Nội dung'] && $existing->category === $category) {
                        $skippedCount++;
                    } else {
                        $existing->update([
                            'content' => $item['Nội dung'],
                            'category' => $category,
                        ]);
                        $updatedCount++;
                    }
                } else {
                    $brand->knowledgeItems()->create([
                        'title' => $item['Tiêu đề'],
                        'category' => $category,
                        'content' => $item['Nội dung'],
                        'is_active' => true,
                        'workspace_id' => $brand->workspace_id ?? 1
                    ]);
                    $createdCount++;
                }
            }
            DB::commit();
            $this->info("Successfully imported! Created: $createdCount, Updated: $updatedCount, Skipped: $skippedCount");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to import: " . $e->getMessage());
        }
    }
}
