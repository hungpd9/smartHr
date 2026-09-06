# Hướng dẫn Quỹ Phép Năm, Nghỉ bù & Tạo Đơn từ

Tài liệu hướng dẫn quản trị số dư ngày phép dành cho **Người lao động (Employee)**.

---

## 1. Cơ cấu Quỹ Phép Năm (Annual Leave Allocation)

Smart-HR tự động tính toán và khởi tạo số dư phép năm cho người lao động bám sát Bộ luật Lao động 2019:

1. **Phép năm luật định cơ bản (Statutory Leave):**
   - Người lao động làm việc đủ 12 tháng được hưởng **12 ngày nghỉ phép năm** hưởng nguyên lương (Điều 113 BLLĐ 2019).
   - Lao động làm việc chưa đủ 12 tháng: Số ngày phép = (Số tháng làm việc / 12) * 12 ngày.
2. **Cộng thêm theo thâm niên làm việc (Seniority Bonus - Điều 114):**
   - Cứ đủ **05 năm làm việc** cho doanh nghiệp, số ngày nghỉ hằng năm được tăng thêm tương ứng **01 ngày**.
3. **Phép tồn chuyển giao sang năm sau (Carried Over Days):**
   - Phép chưa sử dụng hết của năm trước được chuyển sang năm sau (tối đa theo quy định doanh nghiệp, thường từ 3 - 5 ngày).
   - **Hạn chót sử dụng phép tồn:** Ngày **31/03** của năm tài chính. Sau mốc này, hệ thống sẽ tự động đóng và hết hạn số phép tồn.

---

## 2. Quỹ Nghỉ Bù & Quy đổi Làm thêm giờ (Comp-off Accumulation)

- Khi người lao động làm thêm giờ (OT), bạn có thể lựa chọn nhận tiền lương làm thêm giờ hoặc quy đổi sang ngày nghỉ bù (Comp-off).
- Tỷ lệ quy đổi chuẩn: **8.0 giờ làm thêm = 01 ngày nghỉ bù** hưởng lương.
- Số dư ngày nghỉ bù sẽ được theo dõi riêng biệt tại mục **Quỹ nghỉ bù** trong màn hình Số dư phép.

---

## 3. Tạo Đơn Xin Nghỉ & Giới hạn Khóa Thời Gian (Temporal Locking)

Khi tạo đơn xin nghỉ phép hoặc đơn giải trình chấm công:
1. Nhấn **Tạo đơn xin nghỉ** $\to$ Chọn loại phép (Phép năm, Nghỉ bù, Việc riêng hưởng lương, Nghỉ không lương...).
2. Chọn khoảng thời gian nghỉ: Nửa ngày (Sáng/Chiều) hoặc trọn ngày.
3. Nhập lý do và chọn người phê duyệt trực tiếp.

> [!WARNING]
> **Ràng buộc Khóa Thời Gian (Temporal Locking):**  
> Hệ thống sẽ **từ chối tiếp nhận đơn xin nghỉ** nếu ngày nghỉ thuộc vào tháng chấm công đã được Phòng Nhân sự khóa sổ (`lock_attendance = true`). Đối với các tháng đã khóa sổ, mọi can thiệp hồi tố đều bị ngăn chặn để đảm bảo tính toàn vẹn của bảng công và bảng lương đã chi trả.
