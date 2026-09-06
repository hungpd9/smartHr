# Hướng dẫn Trung tâm Quy chế & Bảo toàn Lịch sử (Unified Policy Engine)

Tài liệu nghiệp vụ chuyên sâu dành cho **Quản trị Nhân sự (HR Admin)** về nguyên tắc **Zero-Hardcode** và cơ chế **Effective Dating**.

---

## 1. Nguyên tắc Không Viết cứng (Zero-Hardcode Policy)

Toàn bộ các quy định pháp luật và nội quy lao động trong Smart-HR đều là các tham số cấu hình động, bao gồm:
- Thời gian miễn trừ đi muộn/về sớm (Grace Period).
- Đơn vị làm tròn giờ nghỉ (`leave_unit_hours`, `early_leave_unit_hours`).
- Biểu nấc phạt vi phạm theo số phút và hệ số nhân tần suất.
- Giờ nghỉ tối thiểu giữa 2 ca liên tiếp (mặc định 12 giờ theo Điều 110 BLLĐ).
- Giờ nghỉ tối thiểu hàng tuần (mặc định 24 giờ liên tục theo Điều 111 BLLĐ).
- Danh mục chính sách con nhỏ, phụ cấp và chế độ phúc lợi.

---

## 2. Quản lý Phiên bản Quy chế theo Thời gian (Effective Dating)

Khi doanh nghiệp thay đổi nội quy lao động hoặc khi Nhà nước ban hành quy định mới (ví dụ: điều chỉnh mức lương cơ sở, biểu thuế TNCN mới):
1. **Tuyệt đối không sửa đè lên phiên bản quy chế cũ.**
2. Nhấn nút **Tạo phiên bản mới** $\to$ Đặt mã phiên bản (ví dụ: `POLICY_2026_Q4`) $\to$ Chọn ngày bắt đầu áp dụng (`effective_from`).
3. Hệ thống sẽ tự động sao chép toàn bộ cấu hình từ phiên bản hiện tại sang phiên bản mới để bạn chỉnh sửa.
4. Mọi báo cáo chấm công và tính lương của các tháng trong quá khứ sẽ vẫn tham chiếu chính xác đến phiên bản quy chế tại thời điểm đó, bảo đảm không làm biến dạng dữ liệu lịch sử.

---

## 3. Cơ chế Khóa Bất biến Lịch sử (Temporal Locking)

> [!CAUTION]
> Khi một phiên bản quy chế đã được sử dụng để chốt bảng công hoặc chốt kỳ lương chi trả thực tế:
> - Hệ thống sẽ tự động gắn cờ **ĐÃ KHÓA BẤT BIẾN LỊCH SỬ (`isLocked = true`)**.
> - Mọi thao tác chỉnh sửa hoặc xóa trực tiếp cấu hình trên phiên bản này đều bị chặn ở cả tầng Giao diện (UI) và Tầng lõi Backend (`TemporalLockService::assertPolicyNotLocked`).
> - Muốn thay đổi quy chế, người dùng **bắt buộc phải Tạo phiên bản mới** áp dụng cho kỳ làm việc chưa chốt.

---

## 4. Quản lý Phúc lợi Con nhỏ & Phụ cấp

1. **Phúc lợi Con nhỏ:**
   - Hỗ trợ thêm/sửa/xóa chính sách hỗ trợ con nhỏ ngoài luật định (ví dụ: con từ 1 - 11 tuổi).
   - Có thể ẩn/hiện tạm thời mà không cần xóa chính sách.
2. **Danh mục Phụ cấp:**
   - Thiết lập hình thức áp dụng: Cố định công ty, Cố định cá nhân hoặc Biến động theo tháng.
   - Thiết lập rõ ràng tính chịu thuế TNCN, tính đóng BHXH, tính vào đơn giá làm thêm giờ (OT), và **Trần miễn thuế TNCN**.
