# Hướng dẫn Chu trình Tính lương & Khóa sổ Kỳ lương (Lock Payroll)

Tài liệu quy trình vận hành chu trình tính lương hàng tháng dành cho **Kế toán Tiền lương (Accountant)**.

---

## 1. Điều kiện Tiên quyết: Duyệt Bảng Chấm công Tháng

> [!IMPORTANT]
> **QUY TẮC BẮT BUỘC:**  
> Động cơ tính lương của Smart-HR tự động lọc và kiểm tra trạng thái phê duyệt bảng chấm công của từng nhân viên:
> - Nhân viên có bảng công tháng ở trạng thái **ĐÃ PHÊ DUYỆT (`is_approved = true`)** $\to$ **Được đưa vào chu trình tính lương chính thức**.
> - Nhân viên **CHƯA ĐƯỢC PHÊ DUYỆT (`is_approved = false`)** $\to$ **Hệ thống tự động BỎ QUA KHÔNG TÍNH LƯƠNG** và gửi cảnh báo đến Kế toán cùng Trưởng phòng ban liên quan.

---

## 2. Các Bước Thực hiện Chu trình Tính lương

1. **Bước 1: Kiểm tra Bảng Chấm công Đã Phê duyệt**
   - Vào Bảng chấm công $\to$ Lọc các nhân viên có trạng thái `is_approved = true`. Đảm bảo các Trưởng phòng ban đã hoàn tất duyệt 100%.
2. **Bước 2: Chạy Động cơ Tính Lương (Calculate Payroll)**
   - Hệ thống tự động nạp phiên bản quy chế có hiệu lực tại kỳ tính lương.
   - Bóc tách làm thêm giờ (OT) miễn thuế theo 3 tầng trần.
   - Trích nộp các khoản bảo hiểm bắt buộc theo tỷ lệ và trần quy định.
   - Tính thuế TNCN theo Biểu thuế 5 bậc lũy tiến động (đối với nhân sự chính thức) hoặc khấu trừ toàn phần 10% (đối với người thử việc/vãng lai).
3. **Bước 3: Xem Bảng Lương Dự thảo & Xuất Báo cáo Đối soát**
   - Rà soát Tổng thu nhập (Gross), Giảm trừ, Bảo hiểm, Thuế và Thực lĩnh (Net).

---

## 3. Khóa Kỳ Lương (Lock Payroll) & Bảo vệ Toàn vẹn Dữ liệu

Khi tiền lương đã được chi trả thành công và đối soát xong với Ngân hàng:
1. Nhấn nút **Khóa sổ Kỳ Lương (`Lock Payroll Period`)**.
2. **Tác động của Việc Khóa Kỳ Lương:**
   - Khóa vĩnh viễn bảng chấm công và bảng lương của tháng đó, ngăn ngừa mọi hành vi sửa chữa hồi tố.
   - **Khóa bất biến Quy chế (`PolicyVersion`):** Không thể sửa đổi hoặc xóa các tham số lương, thuế, bảo hiểm của phiên bản quy chế đã dùng để tính lương.
   - **Khóa cấu hình Lương cá nhân:** Không thể xóa hay sửa mức lương cơ bản, phụ cấp đã áp dụng cho nhân viên trong tháng đã khóa.
   - Để áp dụng mức lương hoặc chính sách mới, Kế toán và HR bắt buộc phải ban hành phiên bản mới có ngày hiệu lực bắt đầu từ kỳ chưa khóa.
