# Thiết kế API - AI Facebook Content Tool

Tài liệu này định nghĩa cấu trúc API sẽ được phát triển cho hệ thống.
Tất cả API có prefix `/api/`. 
Giao tiếp mặc định sử dụng định dạng `application/json`.

## 1. Health Check (Sprint 0 - Đã triển khai)
- **Method:** `GET`
- **Endpoint:** `/api/health`
- **Mục đích:** Kiểm tra trạng thái hoạt động của hệ thống backend.
- **Request:** (Không)
- **Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "API is running",
  "data": {
    "application": "AI Facebook Content Tool",
    "environment": "local"
  }
}
```

## 2. Content Generation (Sprint 1 - Sẽ triển khai)
- **Method:** `POST`
- **Endpoint:** `/api/content/generate`
- **Mục đích:** Gọi AI (Ollama) để sinh nội dung Facebook dựa trên dữ liệu người dùng.
- **Request Body:**
```json
{
  "topic": "Mì Omachi",
  "main_information": "Sợi mì làm từ khoai tây...",
  "target_audience": "Sinh viên 18-24 tuổi",
  "objective": "sales",
  "tone": "youthful",
  "length": "medium",
  "required_keywords": ["Omachi"],
  "excluded_content": ["Không nói quá"],
  "number_of_versions": 3
}
```
- **Response thành công (200 OK):**
```json
{
  "success": true,
  "data": {
    "versions": [
      {
        "title": "Tiêu đề",
        "content": "Nội dung...",
        "cta": "Mua ngay!",
        "hashtags": ["#Omachi"]
      }
    ],
    "metadata": {
      "model": "qwen2.5:3b",
      "generated_at": "2026-08-28T15:00:00Z"
    }
  }
}
```

## 3. Quản lý Brands (Dự kiến)
- `GET /api/brands` - Lấy danh sách thương hiệu.
- `POST /api/brands` - Tạo mới thương hiệu.
- `GET /api/brands/{id}` - Xem chi tiết.
- `PUT /api/brands/{id}` - Cập nhật.
- `DELETE /api/brands/{id}` - Xóa.

## 4. Quản lý Posts (Bài viết) (Dự kiến)
- `GET /api/posts` - Lấy danh sách bài viết theo trạng thái.
- `POST /api/posts` - Lưu bài viết nháp (draft).
- `GET /api/posts/{id}` - Xem chi tiết.
- `PUT /api/posts/{id}` - Chỉnh sửa bài viết.
- `DELETE /api/posts/{id}` - Xóa bài viết.
- `POST /api/posts/{id}/submit` - Đổi trạng thái sang `pending_review`.
- `POST /api/posts/{id}/approve` - Duyệt bài -> `approved`.
- `POST /api/posts/{id}/request-revision` - Yêu cầu sửa -> `revision_required`.

## 5. Facebook Pages (Dự kiến)
- `GET /api/facebook/pages` - Danh sách Page đã kết nối.
- `POST /api/facebook/connect` - Nhận callback & lưu token kết nối Page.
- `DELETE /api/facebook/pages/{id}` - Hủy kết nối Page.

## 6. Publishing (Đăng bài) (Dự kiến)
- `POST /api/posts/{id}/publish` - Đăng bài ngay lên Facebook.
- `POST /api/posts/{id}/schedule` - Lên lịch đăng bài.
- `POST /api/posts/{id}/cancel-schedule` - Hủy lịch đăng.
- `POST /api/posts/{id}/retry` - Đăng lại bài bị thất bại (`failed`).

## Quy tắc Response Lỗi chung
```json
{
  "success": false,
  "message": "Mô tả lỗi dễ hiểu cho người dùng",
  "error_code": "MÃ_LỖI",
  "errors": {
     "field_name": ["Lỗi chi tiết validation"]
  }
}
```
