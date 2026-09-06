# Hướng dẫn Cấu hình Tiền lương, Bảo hiểm & Bóc tách OT Miễn thuế

Tài liệu nghiệp vụ chuyên sâu dành cho **Kế toán Tiền lương (Accountant)**.

---

## 1. Định mức Ngày công chuẩn & Mức Giảm trừ Thuế

1. **Chế độ Ngày công chuẩn:**
   - **Cố định (Fixed Mode):** Áp dụng số ngày công khoán cố định (ví dụ: `26.0` ngày/tháng).
   - **Theo lịch thực tế (Calendar Mode):** Tự động tính số ngày làm việc trong tháng trừ đi ngày nghỉ Thứ Bảy và Chủ Nhật theo lịch dương.
2. **Mức Giảm trừ Gia cảnh:**
   - Bản thân: Mặc định `11.000.000` VNĐ/tháng.
   - Người phụ thuộc: Mặc định `4.400.000` VNĐ/người/tháng.
   - Các mức này hoàn toàn có thể tùy chỉnh động trên giao diện khi Nhà nước ban hành nghị quyết điều chỉnh mức giảm trừ gia cảnh mới.

---

## 2. Tỷ lệ Đóng Bảo hiểm Bắt buộc & Mức Lương Trần

Căn cứ quy định Luật Bảo hiểm Xã hội và Luật Việc làm:
- **Tỷ lệ Doanh nghiệp đóng (Tổng 21.5%):**
  - BHXH: `17.5%`
  - BHYT: `3.0%`
  - BHTN: `1.0%`
- **Tỷ lệ Người lao động đóng (Tổng 10.5%):**
  - BHXH: `8.0%`
  - BHYT: `1.5%`
  - BHTN: `1.0%`
- **Mức lương trần đóng bảo hiểm:**
  - Trần đóng BHXH/BHYT: Tối đa 20 lần mức lương cơ sở (ví dụ: $20 \times 2.340.000 = 46.800.000$ VNĐ).
  - Trần đóng BHTN: Tối đa 20 lần mức lương tối thiểu vùng (ví dụ Vùng 1: $20 \times 4.960.000 = 99.200.000$ VNĐ).

---

## 3. Thuật toán 3 Tầng Trần Bóc tách OT Miễn thuế (Nghị định 145/2020)

Khoản tiền trả thêm cho giờ làm việc ban đêm, làm thêm giờ được **miễn thuế TNCN** phần chênh lệch vượt so với tiền lương làm việc bình thường. Smart-HR áp dụng thuật toán kiểm soát 3 tầng trần:

1. **Trần Ngày:** Tối đa không quá 50% số giờ làm việc bình thường trong ngày (ví dụ ca 8h $\to$ OT tối đa 4h).
2. **Trần Tháng:** Số giờ OT bóc tách miễn thuế không vượt quá **40.0 giờ/tháng**.
3. **Trần Năm:** Số giờ OT bóc tách miễn thuế không vượt quá **200.0 giờ/năm** (hoặc 300.0 giờ/năm đối với các trường hợp đặc thù theo quy định).
- Phần giờ làm thêm vượt quá các ngưỡng trần trên vẫn được trả lương đầy đủ cho người lao động nhưng **phải chịu thuế TNCN toàn bộ**.
