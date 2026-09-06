# Hướng dẫn Quản trị Sơ đồ Tổ chức & Hồ sơ Nhân sự (CoreHR)

Tài liệu nghiệp vụ dành cho **Quản trị Nhân sự (HR Admin)**.

---

## 1. Quản lý Cơ cấu Tổ chức & Phòng ban (Departments CRUD)

1. **Khởi tạo Phòng ban mới:**
   - Truy cập **CoreHR** $\to$ Tab **Phòng ban** $\to$ Nhấn **Thêm Phòng ban**.
   - Khai báo Mã đơn vị (ví dụ: `IT`, `FINANCE`), Tên đơn vị, Cấp bậc (Khối cấp 1, Phòng ban cấp 2, Team cấp 3), Chi nhánh trực thuộc, Đơn vị cha và Trưởng đơn vị phụ trách.
2. **Chỉnh sửa Thông tin Phòng ban:**
   - Nhấn nút **Sửa (Icon Bút chì)** trên dòng phòng ban tương ứng.
   - Cập nhật thông tin trưởng phòng, trạng thái (`ACTIVE` / `INACTIVE`), thứ tự hiển thị.
3. **Ban hành Phiên bản Phòng ban mới (Effective Dating):**
   - Khi phòng ban có sự thay đổi lớn về tên gọi hoặc chức năng nhiệm vụ từ một mốc thời gian cụ thể:
   - Nhấn **Phiên bản mới (Effective Dating)** $\to$ Nhập tên mới và Ngày bắt đầu hiệu lực.
   - Hệ thống tự động đóng phiên bản cũ và lưu vết lịch sử vĩnh viễn, bảo toàn tính đúng đắn cho các báo cáo trong quá khứ.
4. **Xóa / Giải thể Phòng ban:**
   - Hệ thống kiểm tra ràng buộc toàn vẹn dữ liệu: **Chỉ cho phép xóa phòng ban khi không có nhân sự nào đang công tác và không có phòng ban con trực thuộc**.
   - Mọi thao tác Xóa đều được ghi vết chi tiết vào Nhật ký Kiểm toán (Audit Trail).

---

## 2. Quản lý Danh mục Chức danh & Vị trí (Positions CRUD)

1. Truy cập **CoreHR** $\to$ Tab **Chức danh (Positions)**.
2. Thiết lập hệ thống chức danh chuẩn doanh nghiệp:
   - Mã chức danh (ví dụ: `SENIOR_DEV`, `HR_LEAD`).
   - Cấp bậc chức danh: Phân tầng từ Level 1 (Nhân viên mới) đến Level 10 (Ban Tổng Giám đốc).
   - Mức phụ cấp trách nhiệm/chức vụ (VNĐ/tháng).
   - Mức thưởng bổ nhiệm 1 lần khi thăng chức (VNĐ).
   - Mô tả tiêu chuẩn công việc và tuyển dụng.

---

## 3. Tiếp nhận Nhân sự & Quản lý Vòng đời (Employee Lifecycle)

- **Tiếp nhận Nhân sự mới (Onboarding):**
  - Khai báo đầy đủ thông tin định danh, CCCD 12 số, mã chấm công phần cứng.
  - Tự động kích hoạt chuỗi sự kiện `employee_career_timelines` mốc `ONBOARDING`.
- **Thôi việc (Offboarding):**
  - Khai báo ngày chính thức chấm dứt hợp đồng, lý do, số quyết định thôi việc và ghi chú bàn giao tài sản.
  - Chuyển trạng thái sang `TERMINATED`, tự động chốt thâm niên và đóng tài khoản đăng nhập.
