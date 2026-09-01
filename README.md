# AI Facebook Content Tool

Dự án nội bộ hỗ trợ tự động hóa sáng tạo nội dung (bằng AI) và đăng bài lên Facebook Page.

## Mục tiêu Hệ thống
- Tự động sinh nội dung chất lượng cao cho Facebook từ một vài mô tả cơ bản.
- Tích hợp trực tiếp với Local AI (Ollama) nhằm tối ưu chi phí và bảo mật dữ liệu.
- Quản lý quá trình kiểm duyệt bài đăng giữa Content Creator và Reviewer.
- Lên lịch và đăng bài tự động lên các trang Facebook đã kết nối thông qua Meta Graph API.

## Công nghệ sử dụng
- **Frontend**: React.js + Vite.
- **Backend**: Laravel 11/10 + SQLite (giai đoạn đầu).
- **AI**: Ollama (model `qwen2.5:3b` - chạy local).
- **Tích hợp mạng xã hội**: Meta Graph API.

## Yêu cầu môi trường
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- Ollama (cài đặt local, API endpoint mặc định `http://localhost:11434`)

## Cấu trúc thư mục
- `backend/`: Mã nguồn Laravel API.
- `frontend/`: Mã nguồn React UI.
- `docs/`: Tài liệu kiến trúc, database, API.

---

## Hướng dẫn Cài đặt & Chạy ứng dụng (Môi trường Windows)

### 1. Cài đặt Backend (Laravel)
Mở terminal và trỏ vào thư mục `backend`:
```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Cấu hình file `.env` sử dụng SQLite (đã được cấu hình mặc định trong Sprint 0):
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

### 2. Cài đặt Frontend (React)
Mở một terminal MỚI và trỏ vào thư mục `frontend`:
```bash
cd frontend
npm install
```

Cấu hình file `.env` (tạo mới file `.env` trong thư mục `frontend`):
```env
VITE_API_BASE_URL=http://localhost:8000/api
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

## Roadmap phát triển
- **Sprint 0**: Khởi tạo project, thiết kế kiến trúc, setup Laravel + React cơ bản, endpoint health check.
- **Sprint 1 (Hiện tại)**: Xây dựng Form tạo nội dung, gọi trực tiếp API Ollama để sinh bài viết theo template. Đã hoàn thiện tính năng tạo content với đầy đủ xử lý lỗi, validation, rate limit.
- **Sprint 2**: Cấu hình cơ sở dữ liệu thật, quản lý người dùng, thương hiệu, lưu trữ bài nháp, duyệt bài.
- **Sprint 3**: Tích hợp Meta Graph API, kết nối Page, đăng bài.
- **Sprint 4**: Lên lịch đăng bài, tự động retry, theo dõi trạng thái, bảo mật.

## Sprint 1 - Sinh nội dung Facebook bằng AI
Tính năng cho phép người dùng điền các thông tin của sản phẩm (chủ đề, tính năng, khách hàng mục tiêu, mục tiêu, độ dài) và nhờ AI sinh ra 1-5 phiên bản bài đăng.

### API: `POST /api/content/generate`
- Tính năng: Gửi nội dung form tới AI (Ollama) và trả về các bài đăng JSON hợp lệ.
- Rate Limit: 5 requests / phút / IP.
- Lỗi xử lý: Timeout, sai cấu trúc JSON, không tìm thấy Model, mất kết nối Ollama.

### Cấu hình Ollama `.env`
Đảm bảo đã cấu hình `.env` trong thư mục `backend`:
```env
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5:3b
OLLAMA_TIMEOUT=120
```

### Các lệnh kiểm tra Model Ollama
- Xem danh sách model: `ollama list`
- Tải model (nếu chưa có): `ollama pull qwen2.5:3b`
- Chạy thử model: `ollama run qwen2.5:3b`

## Sprint 2: Qu?n l� b�i vi?t (�� ho�n th�nh)
- Th�m co s? d? li?u luu tr? b�i vi?t SQLite v?i SoftDeletes.
- H? tr? REST API CRUD: T?o th? c�ng, Luu t? AI, C?p nh?t, X�a m?m, Nh�n b?n, v� Ph�n trang (Pagination).
- Frontend t�ch h?p **React Router** d? chuy?n trang.
- Tr�nh so?n th?o b�i vi?t v?i t�nh nang **Autosave** (t? d?ng luu sau 2 gi�y) v� **Facebook Preview** tr?c quan.
- Ch?c nang Upload ?nh b? t?m ho�n d? t?i uu h�a Editor.

### C�ch ki?m th? (Testing)
- Backend: Ch?y l?nh \php artisan test\ d? ch?y 39 test cases t? d?ng (bao g?m API Posts).
- Ch?y ?ng d?ng: \php artisan serve\ (backend) v� \
pm run dev\ (frontend).
- Tr�n UI, v�o m?c 'B�i vi?t' d? xem danh s�ch, ho?c 'T?o b�i vi?t th? c�ng' d? test Autosave.


## Sprint 3: H? so thuong hi?u v� m?u content (�� ho�n th�nh)
- Qu?n l� H? so thuong hi?u: T�n, ng�nh h�ng, tone, hashtag m?c d?nh, quy t?c vi?t, t? kh�a c?m.
- Qu?n l� M?u n?i dung (Content Template): G?n li?n v?i thuong hi?u, c?u tr�c th�n b�i, hu?ng d?n CTA, v.v.
- T�ch h?p th�ng tin thuong hi?u v� m?u v�o Prompt Generator d? t? d?ng h�a AI t?o b�i.


## Sprint 4: K?t n?i Facebook Page & �ang b�i (�� ho�n th�nh)
- Ho�n thi?n lu?ng x�c th?c OAuth 2.0 an to�n v?i Meta Graph API.
- M� h�a Token an to�n trong Database.
- Qu?n l� Facebook Pages v� ki?m tra tr?ng th�i k?t n?i.
- �ang b�i t? d?ng l�n Page t? ?ng d?ng v?i b? l?c ch?ng dang tr�ng.
- L?ch s? dang b�i chuy�n s�u (Publication Logs).

