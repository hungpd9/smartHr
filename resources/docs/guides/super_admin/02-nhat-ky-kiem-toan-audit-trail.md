# Hướng dẫn Tra cứu Nhật ký Kiểm toán & Truy vết Biến động Dữ liệu

Tài liệu giám sát bảo mật dành cho **Quản trị viên Hệ thống (Super Admin)**.

---

## 1. Vai trò của Nhật ký Kiểm toán (Audit Trail)

Trong một hệ thống quản trị nhân sự và tiền lương doanh nghiệp, tính trung thực và khả năng truy xuất nguồn gốc của dữ liệu là yếu tố sống còn:
- Smart-HR ghi nhận tự động mọi thao tác Tạo mới (`CREATED`), Chỉnh sửa (`UPDATED`), Xóa (`DELETED`), Đổi trạng thái (`STATUS_CHANGED`), và Ban hành quy chế (`POLICY_VERSIONED`).
- Mỗi bản ghi kiểm toán lưu trữ:
  - **Người thực hiện:** Tên, Email và Vai trò tại thời điểm thao tác.
  - **Thời điểm:** Ngày, giờ chính xác đến từng giây.
  - **Địa chỉ IP:** IP thiết bị gửi yêu cầu.
  - **So sánh Biến động (Field Diff):** Giá trị cũ (`old_values`) $\to$ Giá trị mới (`new_values`).

---

## 2. Cách Tra cứu Nhật ký Kiểm toán

1. **Xem Nhật ký theo Từng Đối tượng Cụ thể (Ngay trên Màn hình Quản lý):**
   - Tại màn hình Phòng ban, Chức danh, hoặc Quy chế: Nhấn biểu tượng **Đồng hồ (Lịch sử kiểm toán)** tại hàng tương ứng.
   - Một ngăn kéo (Drawer) sẽ trượt ra từ bên phải màn hình hiển thị Timeline biến động của đúng đối tượng đó.
2. **Xem Nhật ký Kiểm toán Toàn bộ Đối tượng:**
   - Nhấn nút **Lịch sử Kiểm toán** trên thanh công cụ đầu bảng để tra cứu toàn bộ lịch sử biến động của tất cả các phòng ban hoặc chức danh trong hệ thống.
3. **Phân tích Khác biệt Trường (Diff Viewer):**
   - Khi có sự kiện cập nhật (`UPDATED`), hệ thống so sánh và hiển thị các trường bị thay đổi dưới dạng 2 khối màu trực quan:
     - **Màu đỏ (Giá trị cũ):** Dữ liệu trước khi sửa.
     - **Màu xanh lá (Giá trị mới):** Dữ liệu sau khi cập nhật.
