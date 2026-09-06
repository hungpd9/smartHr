# Hướng dẫn Kiểm tra & Phê duyệt Bảng Chấm công Tháng

Tài liệu hướng dẫn bắt buộc dành cho **Cán bộ Quản lý / Trưởng phòng (Manager)** trước khi chuyển dữ liệu sang Kế toán tính lương.

---

## 1. Tầm quan trọng của Việc Phê duyệt Bảng công Tháng

> [!IMPORTANT]
> **QUY TẮC BẮT BUỘC TRONG HỆ THỐNG:**  
> **Nhân viên nào chưa được Trưởng phòng phê duyệt bảng chấm công tháng (`is_approved = false`) sẽ bị hệ thống TỰ ĐỘNG BỎ QUA KHÔNG TÍNH LƯƠNG.**  
> Trưởng phòng ban có trách nhiệm đối soát và hoàn tất phê duyệt bảng công cho 100% nhân sự thuộc quyền quản lý trước ngày chốt công hàng tháng (thường là ngày cuối cùng của tháng hoặc ngày mùng 2 tháng kế tiếp).

---

## 2. Quy trình Rà soát & Phê duyệt Bảng công

1. **Truy cập Bảng Chấm công Tháng:**
   - Vào menu **Chấm công & Bảng công** $\to$ Chọn tháng cần duyệt (ví dụ: `2026-09`).
   - Chọn bộ lọc phòng ban của bạn.
2. **Đối soát Dữ liệu Từng Nhân sự:**
   - Kiểm tra tổng số ngày công thực tế, ngày công hưởng lương.
   - Kiểm tra số giờ làm thêm (OT ngày thường, OT đêm, OT cuối tuần, OT ngày lễ).
   - Kiểm tra các khoản phạt đi muộn / về sớm đã tính đúng theo giải trình hay chưa.
   - Đảm bảo toàn bộ các đơn xin nghỉ phép, đơn giải trình bù công trong tháng đã được xử lý duyệt hoàn tất (không còn ở trạng thái `PENDING`).
3. **Thực hiện Phê duyệt (`Approve Timesheet`):**
   - Nhấn nút **Phê duyệt Bảng công** cho từng nhân viên hoặc chọn nhiều nhân viên để duyệt hàng loạt.
   - Trạng thái bảng công của nhân viên sẽ chuyển sang **ĐÃ PHÊ DUYỆT (APPROVED)**, hiển thị tên người duyệt và thời điểm duyệt.
   - Dữ liệu này sẽ tự động đủ điều kiện sẵn sàng để Bộ phận Kế toán Tiền lương chạy chu trình tính lương chính thức.
