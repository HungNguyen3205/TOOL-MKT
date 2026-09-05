# AI Facebook Content Tool

Dự án nội bộ hỗ trợ tự động hóa sáng tạo nội dung (bằng AI) và đăng bài lên Facebook Page.

## Mục tiêu Hệ thống
- Tự động sinh nội dung chất lượng cao cho Facebook từ một vài mô tả cơ bản.
- Tích hợp trực tiếp với Local AI (Ollama) và OpenAI nhằm tối ưu chi phí và bảo mật dữ liệu.
- Quản lý quá trình kiểm duyệt bài đăng giữa Content Creator và Reviewer.
- Lên lịch và đăng bài tự động lên các trang Facebook đã kết nối thông qua Meta Graph API.

## Công nghệ sử dụng
- **Frontend**: React.js + Vite.
- **Backend**: Laravel 11 + SQLite (giai đoạn đầu).
- **AI**: Ollama (model `qwen2.5:3b` - chạy local) & OpenAI.
- **Tích hợp mạng xã hội**: Meta Graph API v20.0.

## Yêu cầu môi trường
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- Ollama (Cài đặt local, API endpoint mặc định `http://localhost:11434`)

## Cấu trúc thư mục
- `backend/`: Mã nguồn Laravel API.
- `frontend/`: Mã nguồn React UI.
- `docs/`: Tài liệu kiến trúc, database, API và Facebook Integration.

---

## Hướng dẫn Cài đặt & Chạy ứng dụng

### 1. Cài đặt Backend (Laravel)
Mở terminal và trỏ vào thư mục `backend`:
```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Cấu hình file `.env` sử dụng SQLite (đã được cấu hình mặc định):
```env
DB_CONNECTION=sqlite
```

Tạo file database rỗng (nếu chưa có) và chạy migration:
```bash
type nul > database/database.sqlite
php artisan migrate
```

Chạy server Laravel:
```bash
php artisan serve
```
Backend sẽ hoạt động tại `http://localhost:8000`.

*Kiểm tra Health API:* Mở trình duyệt hoặc Postman truy cập vào `http://localhost:8000/api/health`.

### 1.5 Chạy Queue Worker (BẮT BUỘC để đăng bài Facebook)
Hệ thống đăng bài Facebook sử dụng Queue để xử lý bất đồng bộ. Bạn **phải** chạy Worker ở một terminal riêng:
```bash
php artisan queue:work database --queue=facebook-publish,default --tries=3 --timeout=120
```

### 2. Cài đặt Frontend (React)
Mở một terminal mới và trỏ vào thư mục `frontend`:
```bash
cd frontend
npm install
```

Chạy server Frontend:
```bash
npm run dev
```
Giao diện sẽ hiển thị tại `http://localhost:5173`.

### 3. Kiểm thử Backend (Automated Tests)
Bên trong thư mục `backend`, chạy:
```bash
php artisan test
```

---

## Roadmap & Tiến độ Sprints

### Sprint 0: Nền móng Kiến trúc (Đã hoàn thành)
- Khởi tạo project, thiết kế kiến trúc, setup Laravel + React cơ bản, endpoint health check.

### Sprint 1: Sinh nội dung Facebook bằng AI (Đã hoàn thành)
- Xây dựng Form tạo nội dung, gọi trực tiếp API Ollama/OpenAI để sinh bài viết theo template. 
- Đã hoàn thiện tính năng tạo content với đầy đủ xử lý lỗi, validation, rate limit.
- Có khả năng sinh 1-5 phiên bản bài viết trong một lần chạy.

### Sprint 2: Quản lý Bài viết (Đã hoàn thành)
- Thêm cơ sở dữ liệu lưu trữ bài viết SQLite với SoftDeletes.
- Hỗ trợ REST API CRUD: Tạo thủ công, Lưu từ AI, Cập nhật, Xóa mềm, Nhân bản, và Phân trang (Pagination).
- Frontend tích hợp **React Router** để chuyển trang.
- Trình soạn thảo bài viết với tính năng **Autosave** (tự động lưu sau 2 giây) và **Facebook Preview** trực quan y hệt Facebook thật.

### Sprint 3: Hồ sơ Thương hiệu và Mẫu Content (Đã hoàn thành)
- Quản lý Hồ sơ thương hiệu: Tên, ngành hàng, tone giọng, hashtag mặc định, quy tắc viết bài, từ khóa cấm kỵ.
- Quản lý Mẫu nội dung (Content Template): Gắn liền với thương hiệu, cấu trúc thân bài, hướng dẫn CTA, v.v.
- Tích hợp thông minh thông tin Thương hiệu và Mẫu vào Prompt Generator để tự động hóa AI viết bài bám sát nhận diện thương hiệu.

### Sprint 4: Kết nối Facebook Page & Đăng bài (Đã hoàn thành)
- Hoàn thiện luồng xác thực OAuth 2.0 an toàn với Meta Graph API.
- Mã hóa Token bảo mật trong Database.
- Quản lý Facebook Pages và kiểm tra trạng thái kết nối.
- Tính năng đăng bài trực tiếp lên Page từ ứng dụng với bộ lọc chống đăng trùng lặp.
- Lịch sử đăng bài chuyên sâu (Publication Logs).
