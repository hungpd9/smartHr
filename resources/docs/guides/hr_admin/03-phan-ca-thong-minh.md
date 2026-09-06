# Hướng dẫn Động cơ Phân ca Tự động & Tuân thủ Pháp luật Lao động

Tài liệu nghiệp vụ phân ca thông minh dành cho **Quản trị Nhân sự (HR Admin)**.

---

## 1. Ràng buộc Cứng Luật định (Hard Constraints - Bắt buộc)

Động cơ Auto Rostering Engine của Smart-HR tự động áp dụng các điều kiện kiểm tra nghiêm ngặt bám sát Bộ luật Lao động 2019:

1. **Khoảng nghỉ tối thiểu giữa 2 ca làm việc (Điều 110 BLLĐ 2019):**
   - Người lao động phải được nghỉ ít nhất **12 giờ liên tục** trước khi chuyển sang ca làm việc tiếp theo.
2. **Thời gian nghỉ hằng tuần (Điều 111 BLLĐ 2019):**
   - Mỗi tuần, người lao động được nghỉ ít nhất **24 giờ liên tục** (tối thiểu 01 ngày nghỉ trọn vẹn). Tuyệt đối cấm phân ca liên tục 7 ngày trong tuần.
3. **Bảo hộ Thai sản & Con nhỏ (Điều 137 BLLĐ 2019):**
   - Phụ nữ mang thai từ tháng thứ 7: Cấm tuyệt đối ca đêm ($22:00 - 06:00$) và OT.
   - Lao động nuôi con dưới 12 tháng: Mặc định không xếp ca đêm/OT, trừ khi có văn bản thỏa thuận tự nguyện.

---

## 2. Ràng buộc Mềm & Chiến lược Tối ưu (Soft Constraints)

- **Chiến lược Công bằng (Fairness Optimization):**  
  Tự động cân đối số lượng ca đêm, ca cuối tuần giữa các nhân sự cùng vị trí trong phòng ban để tránh dồn gánh nặng cho một cá nhân.
- **Chiến lược Tối ưu Chi phí (Cost Optimization):**  
  Tận dụng tối đa giờ làm việc tiêu chuẩn trong khung giờ thường trước khi phân ca làm thêm giờ, hạn chế tối đa OT chạm các nấc thuế suất cao (cuối tuần 200%, lễ tết 300%).

---

## 3. Thao tác Phân ca Tự động

1. Vào menu **Phân ca** $\to$ Chọn **Ma trận Phân ca Tuần/Tháng**.
2. Chọn phòng ban và khoảng thời gian cần phân ca (ví dụ: Tuần tiếp theo).
3. Nhấn nút **Tự động Phân ca (Auto Schedule)** $\to$ Chọn chiến lược ưu tiên: Công bằng hay Tối ưu chi phí.
4. Hệ thống sẽ chạy thuật toán và đưa ra bảng phân ca đề xuất. Người dùng có thể kéo thả thủ công để điều chỉnh trước khi nhấn **Ban hành Lịch ca chính thức**.
