# Hướng dẫn Chấm công & Theo dõi Thời gian làm việc

Tài liệu hướng dẫn dành cho **Người lao động (Employee)** tại hệ thống Smart-HR.

---

## 1. Xem Ca làm việc & Lịch phân ca Cá nhân

1. Truy cập menu **Phân ca & Ca làm việc** $\to$ Chọn **Ma trận Phân ca Tuần/Tháng**.
2. Hệ thống sẽ hiển thị lịch làm việc theo tuần của bạn với các thông tin chi tiết:
   - **Tên ca**: Ca Sáng, Ca Chiều, Ca Hành chính, hoặc Ca Đêm.
   - **Khung giờ**: Thời gian bắt đầu và kết thúc ca (ví dụ: `08:00 - 17:00`).
   - **Giờ nghỉ giữa ca**: Thời gian nghỉ giải lao (ví dụ: 60 phút).
   - **Chỉ tiêu công**: Số giờ công định mức của ca (ví dụ: 8.0 giờ công).

> [!NOTE]
> Nếu bạn thuộc đối tượng lao động nuôi con nhỏ dưới 12 tháng tuổi hoặc mang thai từ tháng thứ 7, hệ thống sẽ tự động hiển thị biểu tượng bảo hộ theo Điều 137 BLLĐ 2019 và không thể bị phân ca đêm nếu bạn chưa đồng thuận bằng văn bản.

---

## 2. Nguyên tắc Ghi nhận Chấm công & Ghép cặp In/Out

- Hệ thống áp dụng cơ chế **PairInOutPipe** để tự động ghép cặp lần quét vân tay/khuôn mặt đầu tiên trong ngày làm sự kiện **Check-in (Giờ vào)** và lần quét cuối cùng làm sự kiện **Check-out (Giờ ra)**.
- Dữ liệu chấm công từ các máy chấm công tại chi nhánh sẽ được Collector Daemon đẩy về máy chủ theo thời gian thực (Real-time Sync).

---

## 3. Thời gian Miễn trừ & Nấc phạt Đi muộn / Về sớm (Lũy tiến)

Hệ thống chấm công của Smart-HR vận hành theo quy chế **Zero-Hardcode**, các thông số được doanh nghiệp cấu hình và áp dụng tự động:

1. **Thời gian miễn trừ (Grace Period):**
   - Khoảng thời gian cho phép đi muộn hoặc về sớm mà **không bị tính phạt** (thường là 5 - 15 phút tùy quy chế từng phiên bản).
2. **Nấc phạt lũy tiến theo số phút (Minute Tier Penalty):**
   - Muộn từ 1 - 15 phút: Mức phạt bậc 1.
   - Muộn từ 16 - 30 phút: Mức phạt bậc 2.
   - Muộn trên 30 phút: Mức phạt bậc 3 hoặc trừ nửa ngày công.
3. **Hệ số nhân theo tần suất vi phạm (Frequency Multiplier):**
   - Nếu bạn vi phạm đi muộn nhiều lần trong cùng 1 tháng (ví dụ từ lần thứ 3 trở lên), hệ thống sẽ nhân hệ số phạt (ví dụ: `1.5x` hoặc `2.0x`) để khuyến khích ý thức kỷ luật.

> [!TIP]
> Bạn có thể xem trước bảng công tạm tính trong tháng của mình tại màn hình **Bảng chấm công** bất kỳ lúc nào để nắm rõ số phút đi muộn, số giờ làm thêm và số ngày công tích lũy.
