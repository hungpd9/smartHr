# Smart-HR Hardware Simulator (Golang Edge Simulator)

Công cụ dòng lệnh giả lập phần cứng máy chấm công (ZKTeco, Hikvision ADMS) và Edge Collector Daemon gửi dữ liệu quẹt thẻ thực tế về hệ thống Cloud Backend **Smart-HR**.

Công cụ được viết bằng **Golang (Go 1.26+)**, hỗ trợ ký số bảo mật **HMAC-SHA256**, xác thực giấy phép **Ed25519** (`smart-hr.lic`), tự động nhận diện tất cả các chi nhánh đang hoạt động trong hệ thống và tích hợp trực tiếp với bộ động cơ tính công 7-Pipe Pipeline của Laravel Core.

---

## 1. Vị Trí Tệp & Mã Nguồn

- **Tệp thực thi:** [`bin/simulator.exe`](file:///c:/FlyEnv/www/smartHr/bin/simulator.exe)
- **Mã nguồn Go:** [`cmd/simulator/main.go`](file:///c:/FlyEnv/www/smartHr/cmd/simulator/main.go)
- **Mô-đun bản quyền:** [`internal/license/license.go`](file:///c:/FlyEnv/www/smartHr/internal/license/license.go)
- **API Tiếp nhận Backend:** [`app/Modules/Licensing/Controllers/AttendanceSyncController.php`](file:///c:/FlyEnv/www/smartHr/app/Modules/Licensing/Controllers/AttendanceSyncController.php)
- **Endpoints API:**
  - `GET /api/v1/attendance/branches`: Cung cấp danh sách tất cả các chi nhánh đang hoạt động trong hệ thống.
  - `GET /api/v1/attendance/employees?branch_code={code}`: Cung cấp danh sách nhân sự thực tế theo chi nhánh.
  - `POST /api/v1/attendance/sync`: Tiếp nhận lô quẹt thẻ có chữ ký số HMAC-SHA256.

---

## 2. Mô Phỏng Toàn Công Ty (Khuyên Dùng Để Test Đầy Đủ Hệ Thống)

Mặc định, `simulator.exe` hỗ trợ chế độ **`ALL`** — tự động quét tất cả các chi nhánh hiện có (`HN_HQ`, `DN_HUB`, `PQ_STORE`, `HCM_BRANCH`), lấy toàn bộ ~100 nhân viên của công ty và sinh các lô quẹt thẻ với các mã máy chấm công riêng cho từng chi nhánh.

### 2.1 Bơm tức thì cả ngày cho toàn bộ công ty (`-mode=fast`)
Chỉ 1 câu lệnh duy nhất để lấp đầy dữ liệu chấm công cả ngày của tất cả chi nhánh và kích hoạt tính công cho toàn bộ công ty:
```powershell
# Mô phỏng tức thì cả công ty cho ngày hôm nay (mặc định -branch=ALL)
.\bin\simulator.exe -branch=ALL -mode=fast

# Hoặc mô phỏng cả công ty cho một ngày cụ thể trong quá khứ/tương lai
.\bin\simulator.exe -branch=ALL -mode=fast -date="2026-09-15"
```
*Kết quả:* Tự động quét 4 chi nhánh, phân bổ 100 nhân sự thành các nhóm Đúng giờ (85%), Đi muộn (10-15%), Vắng mặt (1 người/chi nhánh) và Làm thêm OT (1/3 nhân sự), đẩy 192 lượt quẹt thẻ và tính công cho toàn công ty tức thì!

### 2.2 Chế độ Thường Trực Thời Gian Thực cho toàn công ty (`-mode=daemon`)
Chạy nền giám sát theo đồng hồ thời gian thực và đồng loạt gửi dữ liệu cho tất cả các chi nhánh:
```powershell
.\bin\simulator.exe -branch=ALL -mode=daemon
```
**Lịch trình tự động trong ngày:**
- **07:40 - 08:05:** Tự động gửi Check-in Đúng giờ cho 80% - 90% nhân sự của cả 4 chi nhánh.
- **08:20 - 08:45:** Tự động gửi Check-in Đi muộn cho số nhân sự còn lại.
- **17:00 - 18:00:** Tự động gửi Check-out (cả về đúng giờ và làm thêm giờ OT).
- **00:00 (Nửa đêm):** Tự động phát hiện ngày mới và reset chu trình.
- Nhấn `Ctrl + C` để dừng tiến trình an toàn.

---

## 3. Mô Phỏng Theo Từng Chi Nhánh Đơn Lẻ (Single Branch)

Nếu chỉ muốn kiểm thử riêng một chi nhánh cụ thể:

```powershell
# 1. Hội sở chính Hà Nội
.\bin\simulator.exe -branch="HN_HQ" -mode=fast -date="2026-09-15"

# 2. Trung tâm R&D Công nghệ Đà Nẵng
.\bin\simulator.exe -branch="DN_HUB" -mode=fast -date="2026-09-15"

# 3. Trung tâm Phân phối & Cửa hàng Huyện đảo Phú Quốc
.\bin\simulator.exe -branch="PQ_STORE" -mode=fast -date="2026-09-15"

# 4. Chi nhánh TP. Hồ Chí Minh
.\bin\simulator.exe -branch="HCM_BRANCH" -mode=fast -date="2026-09-15"
```

Các chế độ vào/ra ca đơn lẻ:
```powershell
# Chỉ gửi Check-in buổi sáng
.\bin\simulator.exe -branch="HN_HQ" -mode=checkin -date="2026-09-16"

# Chỉ gửi Check-out buổi chiều
.\bin\simulator.exe -branch="HN_HQ" -mode=checkout -date="2026-09-16"
```

---

## 4. Bảng Tham Số Dòng Lệnh Đầy Đủ (CLI Flags)

| Tham số | Mặc định | Ý nghĩa & Mô tả |
| :--- | :--- | :--- |
| `-branch` | `ALL` | Mã chi nhánh (`HN_HQ`, `DN_HUB`, `PQ_STORE`, `HCM_BRANCH`) hoặc `ALL` / `*` để mô phỏng toàn bộ công ty. |
| `-mode` | `fast` | Chế độ chạy: `fast` (bơm tức thì cả ngày), `daemon` (thời gian thực), `checkin` (chỉ vào ca), `checkout` (chỉ ra ca). |
| `-date` | Ngày hiện tại | Ngày mô phỏng theo định dạng `YYYY-MM-DD` (Ví dụ: `2026-09-15`). |
| `-server` | `http://smart-hr.test` | URL gốc của máy chủ Smart-HR Cloud Backend. |
| `-lic` | `smart-hr.lic` | Đường dẫn đến tệp giấy phép bản quyền Ed25519. |
| `-secret` | `""` *(rỗng)* | Khóa bí mật HMAC-SHA256 tùy chỉnh (nếu muốn ghi đè khóa trong license). |
| `-device` | `""` *(tự sinh)* | Số sê-ri máy chấm công giả lập. Nếu để trống, simulator tự sinh mã riêng theo chi nhánh: `ZK-<BRANCH>-SIM-01`. |

---

## 5. Kiến Trúc Vận Hành & Luồng Xử Lý (Data Flow)

```
[ simulator.exe ]
       │
       ├─ 1. Đọc & Xác thực chữ ký Ed25519 từ tệp `smart-hr.lic`
       │     └─ Trích xuất Shared HMAC Secret & Allowed Domain (smart-hr.test)
       │
       ├─ 2. Nếu -branch=ALL: Gọi GET /api/v1/attendance/branches
       │     └─ Tự động phát hiện 4 chi nhánh hoạt động: HN_HQ, DN_HUB, PQ_STORE, HCM_BRANCH
       │
       ├─ 3. Với từng chi nhánh:
       │     ├─ Gọi GET /api/v1/attendance/employees?branch_code=...
       │     ├─ Thiết lập mã máy tương ứng: ZK-HN_HQ-SIM-01, ZK-DN_HUB-SIM-01...
       │     └─ Lập lịch & Sinh quẹt thẻ thực tế:
       │           ├─ Nhóm 1 (85%): Đúng giờ (07:40 - 08:05)
       │           ├─ Nhóm 2 (10-15%): Đi muộn (08:20 - 08:45)
       │           ├─ Nhóm 3 (1 người): Vắng mặt
       │           ├─ Check-out: Đúng giờ (17:01 - 17:15) hoặc OT (17:35 - 18:45)
       │           └─ Phương thức: 70% Vân tay, 25% Khuôn mặt, 5% Thẻ từ
       │
       ├─ 4. Đóng gói JSON & Ký số HMAC-SHA256:
       │     └─ Header: X-SmartHR-Signature: <hmac_hex>
       │     └─ Header: X-SmartHR-Branch: <branch_code>
       │
       ▼ (HTTP POST /api/v1/attendance/sync)
[ Laravel Core Backend (AttendanceSyncController) ]
       │
       ├─ 5. Thẩm định chữ ký HMAC với License hoặc branch secret_key
       ├─ 6. Lưu các bản ghi không trùng lặp vào bảng `raw_attendance_logs`
       └─ 7. Tự động kích hoạt 7-Pipe Pipeline (AttendancePipelineService):
             ├─ PairInOutPipe: Ghép cặp Check-in / Check-out hợp lệ
             ├─ ShiftMatchingPipe: Khớp ca làm việc thực tế
             ├─ ChildPolicyPipe: Kiểm tra chính sách con nhỏ (miễn trừ 60p)
             ├─ MinuteTierLateEarlyPipe: Tính lũy tiến nấc phạt đi muộn / về sớm
             ├─ FrequencyTierPenaltyPipe: Nhân hệ số phạt theo tần suất vi phạm trong tháng
             ├─ TieredOvertimePipe: Bóc tách giờ làm thêm OT lũy tiến & ca đêm
             └─ Cập nhật ngay lập tức vào bảng công ngày `daily_attendances`
```

---

## 6. Hướng Dẫn Biên Dịch Lại (Rebuild)

Khi có chỉnh sửa logic sinh dữ liệu trong [`cmd/simulator/main.go`](file:///c:/FlyEnv/www/smartHr/cmd/simulator/main.go), thực thi lệnh sau từ thư mục gốc của dự án:

```powershell
go build -o bin/simulator.exe ./cmd/simulator
```

---

## 7. Xử Lý Sự Cố Thường Gặp (Troubleshooting)

1. **Lỗi `Không đọc được smart-hr.lic`:**
   - Đảm bảo tệp `smart-hr.lic` nằm ở thư mục làm việc hiện tại hoặc truyền cờ `-lic="đường_dẫn/smart-hr.lic"`.
   - Nếu không có file license, simulator sẽ tự động fallback về khóa bí mật mặc định của chi nhánh `HN_HQ` trong cơ sở dữ liệu (`d250fe6be5bdc1f9e14bdb55cb93ff4d`).

2. **Lỗi `không thể kết nối tới máy chủ (http://smart-hr.test)`:**
   - Đảm bảo máy chủ Web (Nginx / Apache / PHP built-in server qua Laragon/FlyEnv) đang chạy và domain `smart-hr.test` đã trỏ vào `127.0.0.1` trong file `hosts`.
   - Nếu chạy qua cổng khác, bổ sung cờ `-server="http://127.0.0.1:8000"`.

3. **Lỗi `Xác thực thất bại! Chữ ký HMAC không hợp lệ`:**
   - Kiểm tra xem tệp `smart-hr.lic` của backend và của simulator có cùng một phiên bản không.
   - Có thể truyền cờ `-secret="<secret_key_cua_branch>"` lấy từ cột `secret_key` trong bảng `branches` của CSDL.
