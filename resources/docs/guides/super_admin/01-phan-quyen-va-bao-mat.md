# Hướng dẫn Ma trận Phân quyền & Quản trị Bảo mật

Tài liệu quản trị tối cao dành cho **Quản trị viên Hệ thống (Super Admin)**.

---

## 1. Ma trận Phân quyền 5 Vai trò (RBAC Framework)

Hệ thống Smart-HR chuẩn hóa phân quyền thành 5 vai trò độc lập:

1. **`SUPER_ADMIN` (Quản trị Tối cao):** Toàn quyền kiểm soát hệ thống, quản lý chi nhánh, tenant, license bản quyền, ma trận quyền và truy vết kiểm toán toàn diện.
2. **`HR_ADMIN` (Quản trị Nhân sự):** Quản lý hồ sơ nhân sự, cơ cấu tổ chức, chức danh, phiên bản quy chế chấm công, phân ca tự động và quản lý ngày nghỉ phép.
3. **`ACCOUNTANT` (Kế toán Tiền lương):** Quản lý cấu hình tiền lương, biểu thuế TNCN, phụ cấp, chạy chu trình tính lương, khóa kỳ lương và xuất bảng thanh toán.
4. **`MANAGER` (Cán bộ Quản lý):** Quản lý nhân sự trong phòng ban trực thuộc, phê duyệt đơn từ (nghỉ phép, OT, đổi ca), giám sát trần làm thêm giờ và **phê duyệt bảng chấm công tháng**.
5. **`EMPLOYEE` (Người lao động):** Xem ca làm việc cá nhân, kiểm tra lịch sử chấm công, gửi đơn xin nghỉ phép, đơn làm thêm giờ, yêu cầu đổi ca và xem phiếu lương cá nhân.

---

## 2. Bảo vệ Bản quyền & Toàn vẹn Mã hóa (Cryptographic Anchoring)

- Smart-HR sử dụng cơ chế bảo vệ bản quyền cặp đôi đối xứng (**Symmetric Coupled Pair**).
- Tệp `smart-hr.lic` có chữ ký số Ed25519 được dẫn xuất thành khóa mã hóa nội bộ (`DEK`).
- Các trường thông tin nhạy cảm như Chi tiết tiền lương, Số CMND/CCCD được mã hóa mức cơ sở dữ liệu.
- Hệ thống chỉ giải mã và hiển thị khi license hợp lệ và người dùng có thẩm quyền truy cập tương ứng.
