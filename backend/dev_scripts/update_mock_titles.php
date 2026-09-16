<?php

use App\Models\Post;

$titles = [
    "5 Bài tập Yoga giúp giảm đau lưng hiệu quả tại nhà",
    "Khai giảng lớp Yoga Thiền cơ bản tháng 9",
    "Bí quyết hít thở đúng cách trong Yoga",
    "Chương trình khuyến mãi giảm 30% khóa học Yoga Online",
    "Lợi ích tuyệt vời của việc tập Yoga vào buổi sáng",
    "Hướng dẫn thực hiện tư thế Chó úp mặt (Downward Dog)",
    "Yoga cho người mới bắt đầu: Cần chuẩn bị những gì?",
    "Ăn gì trước và sau khi tập Yoga để đạt hiệu quả tốt nhất?",
    "Sự kiện Giao lưu cùng chuyên gia Yoga Ấn Độ",
    "Tại sao Yoga lại giúp giảm căng thẳng và stress?",
    "Tư thế chiến binh: Cách thực hiện và lợi ích",
    "Mẹo giữ thăng bằng trong các tư thế khó",
    "Đăng ký ngay lớp Yoga Trị Liệu cho dân văn phòng",
    "Khách hàng nói gì về khóa học Yoga 1-kèm-1 của chúng tôi?",
    "Top 3 thảm tập Yoga tốt nhất năm 2026",
    "Workshop: Khám phá luân xa và thiền định",
    "Yoga và hành trình thay đổi bản thân",
    "Cảnh báo 3 sai lầm thường gặp khi tự tập Yoga tại nhà",
    "Làm thế nào để duy trì thói quen tập luyện hàng ngày?",
    "Cập nhật lịch học các lớp Yoga tháng 10/2026"
];

$posts = Post::all();

foreach ($posts as $post) {
    // Pick a random realistic title
    $randomTitle = $titles[array_rand($titles)];
    $post->title = $randomTitle;
    
    // Also change the content slightly to make it look real
    $post->content = "Đây là nội dung chi tiết cho bài viết: " . $randomTitle . ". Bài viết này cung cấp những thông tin vô cùng hữu ích cho cộng đồng yêu thích Yoga.";
    
    $post->save();
}

echo "Updated " . count($posts) . " posts with realistic Vietnamese titles.\n";
