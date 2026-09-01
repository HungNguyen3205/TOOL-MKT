# Tích hợp Facebook Graph API & OAuth

Tài liệu này giải thích cách hoạt động của hệ thống kết nối và đăng bài lên Facebook Page (Sử dụng Graph API v20.0).

## 1. Cấu hình Meta App (Facebook Developer)

Để ứng dụng hoạt động, bạn cần tạo một **Meta App** tại [Facebook Developers](https://developers.facebook.com/) và thiết lập:
- Loại ứng dụng: **Business** (hoặc loại hỗ trợ Pages API).
- Thêm sản phẩm: **Facebook Login**.

**Trong cài đặt Facebook Login:**
- Bật **Web OAuth Login**.
- Cấu hình **Valid OAuth Redirect URIs**: Nhập chính xác giá trị của `FACEBOOK_REDIRECT_URI` trong file `.env` (Ví dụ: `http://localhost:8000/api/facebook/callback`).

**Permissions (Quyền yêu cầu):**
- `pages_show_list`: Để ứng dụng có thể lấy danh sách các Page bạn quản lý.
- `pages_manage_posts`: Cho phép ứng dụng tạo, chỉnh sửa và xóa bài đăng trên Page.
- `pages_read_engagement`: Để đọc các chỉ số tương tác hoặc xác minh bài viết.

## 2. Token Lifecycle & Lưu trữ an toàn

1. **OAuth Flow:** Người dùng được chuyển hướng tới Facebook với một mã `state` (Random String lưu trong Cache 15 phút) để chống CSRF.
2. **Callback:** Sau khi người dùng đồng ý, Facebook trả về `code` và `state`. Backend kiểm tra `state`, nếu khớp sẽ dùng `code` đổi lấy **User Access Token**.
3. **Fetching Pages:** Dùng User Token gọi API `/me/accounts` để lấy danh sách Page và **Page Access Token**.
4. **Temporary Session:** Danh sách Page được lưu vào Cache với một `session_id` tạm thời (có hiệu lực 30 phút). User Token sẽ bị loại bỏ và không lưu trữ dài hạn.
5. **Connect:** Khi người dùng chọn kết nối một Page trên Frontend, Backend sẽ trích xuất **Page Access Token** từ Cache, **mã hóa (Encrypt)** nó và lưu vào Database (`facebook_pages`). Token **tuyệt đối không** xuất hiện trên Frontend.

## 3. Luồng Đăng bài (Publishing Flow)

1. Bài viết phải ở trạng thái `ready` (đã được duyệt).
2. Người dùng nhấn "Đăng lên Facebook", chọn Page và xác nhận thao tác.
3. API `/posts/{post}/publish` được gọi:
   - Hệ thống tự động tạo một `PublicationLog` ở trạng thái `processing` để **khóa (lock)**, chống việc request kép (double-submit) gây đăng trùng.
   - Nội dung được format chính xác với khoảng trắng, dòng trống, hashtags.
   - Gửi request đến Endpoint: `POST /{page_id}/feed`.
   - Lưu kết quả `facebook_post_id` (nếu thành công) hoặc lưu lỗi (nếu thất bại) vào `PublicationLog`.

## 4. Hạn chế của Development Mode

Khi Meta App đang ở trạng thái **Development Mode**:
- Ứng dụng chỉ có thể kết nối các Page thuộc quyền quản lý của các tài khoản Facebook được thêm vào **App Roles (Roles > Administrators / Developers / Testers)**.
- Người dùng bình thường sẽ không thể sử dụng chức năng này.
- **Để đưa lên Production**, bạn cần:
  - Hoàn thành **Business Verification**.
  - Submit **App Review** cho các quyền `pages_show_list`, `pages_manage_posts`, `pages_read_engagement`.
  - Chuyển App sang trạng thái **Live Mode**.

## 5. Hướng dẫn Test Thủ công

1. Lấy `App ID` và `App Secret` của Meta App đưa vào `.env`.
2. Mở trình duyệt và truy cập trang chủ của hệ thống.
3. Trên thanh Menu, chọn **FB Pages**.
4. Bấm **Thêm kết nối Facebook**. Ứng dụng sẽ chuyển hướng tới Facebook.
5. Cấp quyền ứng dụng và chọn Page Test.
6. Sau khi quay lại ứng dụng, danh sách các Page sẽ hiện ra, chọn **Kết nối**.
7. Quay lại mục Bài viết, sửa một bài nháp (draft) và đổi trạng thái thành **Sẵn sàng**.
8. Bấm nút **Đăng lên Facebook**, đánh dấu xác nhận và chọn "Xác nhận Đăng".
9. Kiểm tra xem bài đăng đã xuất hiện trên Facebook Page Test của bạn hay chưa!
