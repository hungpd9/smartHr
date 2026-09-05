# GEMINI.md — Rules & Guidelines for Smart-HR

Dự án: **Smart-HR (Intelligent Multi-Tenant HRMS & Rostering Platform)**  
Mô hình mục tiêu: Quản lý nguồn nhân lực toàn diện, chấm công lũy tiến, phân ca thông minh, tính lương và duyệt biểu mẫu đa cấp bám sát Bộ luật Lao động Việt Nam 2019 và các quy định pháp luật hiện hành.

---

## 1. KIẾN TRÚC HỆ THỐNG & CÔNG NGHỆ CHỦ ĐẠO

Hệ thống được cấu thành từ các thành phần cốt lõi:
1. **Core Cloud Backend (Laravel 13 / PHP 8.4+)**:
   - Thiết kế theo kiến trúc **Modular Monolith** phân chia thành 11 Bounded Contexts độc lập (`CoreHR`, `PolicyEngine`, `Attendance`, `Rostering`, `LeavePolicy`, `Workflow`, `Payroll`, `Compliance`, `Reporting`, `Notification`, `Licensing`).
   - Cơ sở dữ liệu: **MySQL 8.0** (thiết kế chuẩn hóa Multi-Tenant `tenant_id` + `branch_id`, bảng log thô bất biến `raw_attendance_logs`, mô hình dữ liệu lịch sử theo thời gian `Effective Dating / Temporal Model`).
   - Caching & Asynchronous Queue: **Redis** (Laravel Horizon, xử lý hàng đợi tính công và đồng bộ thiết bị).
2. **Frontend UI Layer (Inertia.js + Vue 3 Composition API)**:
   - Giao diện người dùng hiện đại xây dựng trên **Vue 3 (Composition API)** kết hợp **Inertia.js** (kết nối trực tiếp Controller Laravel mà không cần tách rời REST API).
   - Thư viện Component chuẩn doanh nghiệp: **Element Plus** kết hợp **TailwindCSS** (100% MIT License, hỗ trợ Table phân trang/lọc đa cột/fixed columns, Timeline nghề nghiệp, Calendar ca kíp, Drag & Drop phân ca).
3. **Edge Collector Daemon (Golang Go 1.26+)**:
   - Chạy trên máy tính thu ngân, server nội bộ hoặc Raspberry Pi tại từng chi nhánh/nhà kho trong mạng LAN.
   - Kết nối trực tiếp máy chấm công qua Socket UDP/TCP port `4370` (ZKTeco protocol) hoặc HTTP (Hikvision ISAPI / ADMS push).
   - Lưu trữ đệm ngoại tuyến (Offline-first) bằng **SQLite** nội bộ khi mất Internet.
   - Đồng bộ dữ liệu theo lô (Batch Sync) lên Laravel Core qua HTTPS REST API có chữ ký bảo mật **HMAC-SHA256**.
4. **Bảo vệ Bản quyền (Symmetric Coupled Pair)**:
   - File nhị phân Go Daemon và Laravel Web dùng chung 01 tệp `smart-hr.lic` có chữ ký số Ed25519 của tác giả.
   - Dẫn xuất khóa `DEK` mã hóa trường lương/CCCD trong CSDL (Cryptographic Data Anchoring). Không có license hợp lệ thì Go không gửi log và CSDL không giải mã được lương.

---

## 2. NGUYÊN TẮC THIẾT KẾ KHÔNG HARDCODE (ZERO-HARDCODE POLICY)

Agent **TUYỆT ĐỐI KHÔNG** hardcode các tham số nghiệp vụ, tỷ lệ tính toán hoặc quy định pháp luật vào mã nguồn:
1. **Trung tâm Cấu hình Quy chế (Unified Policy Engine)**:
   - Toàn bộ nấc phạt muộn/sớm, thời gian miễn trừ (grace period), đơn vị làm tròn (`leave_unit`, `early_leave_unit`), giờ tự động checkout, hệ số OT, giờ nghỉ tối thiểu giữa ca, và tỷ lệ bảo hiểm đều phải được lưu trữ trong bảng phiên bản cấu hình (`policy_versions`, `attendance_policy_configs`).
   - Mọi thay đổi pháp luật hoặc nội quy chỉ cần tạo một phiên bản cấu hình mới với mốc thời gian áp dụng (`effective_from` $\to$ `effective_to`), đảm bảo dữ liệu quá khứ không bị thay đổi.
2. **Biểu thuế TNCN Lũy tiến Động (5 Bậc)**:
   - Áp dụng biểu thuế TNCN 5 bậc hiệu lực từ tháng 01/2026. Bảng `pit_tax_brackets` gắn liền với phiên bản chính sách, không viết cứng các ngưỡng thu nhập và thuế suất trong code.
3. **Cơ chế Phúc lợi Con nhỏ & Gia đình Linh hoạt**:
   - Chế độ con dưới 12 tháng (60 phút có lương/ngày, cấm ca đêm/OT) là quy định luật bắt buộc (default statutory).
   - Các chế độ mở rộng của từng doanh nghiệp (như con 1-11 tuổi, con 1-6 tuổi...) được cấu hình động qua bảng `child_welfare_policies` với các tùy chọn: độ tuổi áp dụng, thời gian miễn trừ, loại hưởng lương (`PAID`, `UNPAID`, `PARTIAL`), hỗ trợ doanh nghiệp bật/tắt linh hoạt.

---

## 3. LỊCH SỬ TỔ CHỨC & TIMELINE NGHÂN SỰ (EFFECTIVE DATING)

1. **Sơ đồ Phòng ban có Lineage**:
   - Không dùng cơ chế enum trạng thái cứng; quản lý phiên bản phòng ban (`department_versions`) với đầy đủ lịch sử đổi tên, chức năng nhiệm vụ, người quản lý trong khoảng thời gian `[effective_date, end_date]`.
2. **Cây Timeline Nghề nghiệp Nhân sự (`employee_career_timelines`)**:
   - Lưu trữ chuỗi sự kiện vòng đời nhân sự (Onboarding, Điều chuyển phòng ban/chi nhánh, Bổ nhiệm chức vụ, Tăng lương, Nghỉ việc).
   - Mọi câu truy vấn dữ liệu quá khứ phải dựa trên mốc thời gian ngày công (`effective_date <= target_date AND (end_date IS NULL OR end_date >= target_date)`).

---

## 4. TIÊU CHUẨN NGHIỆP VỤ CỐT LÕI (CORE DOMAIN CONSTRAINTS)

### 4.1 Động cơ Tính công Lũy tiến & Bảng công Tạm tính
- Triển khai theo mô hình **Pipeline Pattern** với các Pipe xử lý độc lập (`PairInOutPipe`, `ShiftMatchingPipe`, `ChildPolicyPipe`, `TieredOvertimePipe`, `MinuteTierLateEarlyPipe`, `FrequencyTierPenaltyPipe`).
- **3 Loại Lũy tiến**:
  1. *Lũy tiến OT*: Nấc 1 ($\le 2$h) 1.5x, Nấc 2 ($> 2$h) 2.0x, Cuối tuần 2.0x, Lễ tết 3.0x, Phụ cấp ca đêm 30%, OT đêm 200%/270%/390%.
  2. *Lũy tiến phạt muộn/sớm theo số phút*: Biểu nấc động từ cấu hình policy.
  3. *Lũy tiến phạt theo tần suất*: Nhân hệ số phạt theo số lần vi phạm lũy kế trong tháng của nhân viên.
- **Tách biệt Bảng công Tạm tính & Đã duyệt**:
  - Hỗ trợ xem trước bảng công qua `TimesheetPreviewService` (áp dụng các đơn đang ở trạng thái chờ duyệt) bên cạnh bảng công chính thức, không chặn nhân viên tạo đơn mới khi bảng công chưa khóa.

### 4.2 Động cơ Phân ca Tự động (Auto Shift Rostering Engine)
- **Ràng buộc cứng (Hard Constraints - Bắt buộc theo Luật Lao động 2019)**:
  - Nghỉ tối thiểu giữa 2 ca liên tiếp: ít nhất **12 giờ** (Điều 110 BLLĐ 2019).
  - Tối thiểu **01 ngày nghỉ liên tục (24 giờ)** mỗi tuần (Điều 111 BLLĐ 2019).
  - Giờ làm việc bình thường không quá 8h/ngày hoặc 48h/tuần.
  - Tuyệt đối cấm phân ca đêm ($22:00 - 06:00$) hoặc làm thêm giờ đối với lao động nữ mang thai từ tháng thứ 7 (Điều 137 Khoản 1 Điểm a BLLĐ 2019).
  - Đối với lao động nuôi con dưới 12 tháng tuổi: Mặc định không phân ca đêm hoặc OT, TRỪ TRƯỜNG HỢP người lao động có đơn thỏa thuận/đồng thuận tự nguyện (Điều 137 Khoản 1 Điểm b BLLĐ 2019). Khi tạo đơn OT/Ca đêm, hệ thống yêu cầu ký cam kết tự nguyện và nhắc nhở Approver kiểm tra văn bản đồng thuận trước khi phê duyệt.
- **Ràng buộc mềm (Soft Constraints - Chiến lược tối ưu)**:
  - *Chế độ Công bằng (Fairness)*: Cân bằng số ca đêm, ca cuối tuần giữa các nhân viên cùng vị trí.
  - *Chế độ Tối ưu chi phí (Cost Optimization)*: Hạn chế tối đa OT chạm nấc giá cao.

### 4.3 Động cơ Tính Lương (Payroll Engine)
- Bóc tách tiền làm thêm giờ (OT) miễn thuế theo thuật toán 3 tầng trần (ngày 50% ca, tháng 40h, năm 200h/300h theo NĐ 145/2020).
- Biểu thuế TNCN 5 bậc lũy tiến động và trích nộp bảo hiểm bắt buộc theo luật hiện hành.
- Khóa tháng (`isLocked`): Ngăn chặn can thiệp hồi tố dữ liệu công và lương khi đã đóng kỳ.

---

## 5. QUY CHUẨN MÃ NGUỒN & KỸ THUẬT (CODE STANDARDS)

### 5.1 Backend Laravel (PHP 8.4+ / Laravel 13)
- Tuân thủ nghiêm ngặt **PSR-12**, sử dụng `declare(strict_types=1);`.
- Sử dụng đầy đủ Type Hinting cho arguments và return types.
- Phân tầng kiến trúc rõ ràng:
  - `app/Modules/{ModuleName}/Controllers`: Chỉ làm nhiệm vụ điều hướng Request / Response thông qua Inertia Render hoặc JsonResponse.
  - `app/Modules/{ModuleName}/Requests`: Chứa toàn bộ logic validate dữ liệu đầu vào.
  - `app/Modules/{ModuleName}/Services`: Chứa toàn bộ nghiệp vụ xử lý chính (Business Logic).
  - `app/Modules/{ModuleName}/Pipelines`: Chứa các Pipe xử lý tính toán.
  - `app/Modules/{ModuleName}/Models`: Khai báo quan hệ, casts, scopes; không viết logic nghiệp vụ phức tạp trong Model.
- Xử lý cơ sở dữ liệu:
  - Bắt buộc kiểm soát eager loading (`with()`) để triệt tiêu lỗi N+1 Query.
  - Đối với bảng log thô và bảng công lớn, sử dụng Query Builder, Chunking hoặc Lazy Loading (`lazyById()`, `cursor()`).

### 5.2 Frontend Layer (Vue 3 / Inertia.js / Element Plus - Tiêu chuẩn Doanh nghiệp Chuẩn mực)
- **Kiến trúc Server-Driven SPA**: Sử dụng **Inertia.js v2** kết nối trực tiếp Controller Laravel với Vue 3 mà không cần duy trì REST API và Pinia state trùng lặp.
- **Quy chuẩn Vue 3**: Bắt buộc sử dụng **Composition API** với cú pháp `<script setup lang="ts">` hoặc `<script setup>`.
- **Thư viện UI mã nguồn mở**: Sử dụng **Element Plus (100% MIT License)** kết hợp **TailwindCSS**. Tuyệt đối không phụ thuộc theme thương mại mua ngoài hay thư viện có kiểm tra license trả phí.
- **Hỗ trợ Đồng bộ Chế độ Sáng / Tối (Dark & Light Mode)**:
  - Bắt buộc import `element-plus/theme-chalk/dark/css-vars.css` để mọi component (Table, Dialog, Card, Input, Tag) tự động đổi màu tương thích hoàn toàn khi chuyển đổi dark mode.
  - Trạng thái theme phải được lưu vết trong `localStorage` và duy trì xuyên suốt các phiên làm việc.
- **Khung Đa ngôn ngữ (i18n Readiness)**:
  - Khởi tạo sẵn khung đa ngôn ngữ (Tiếng Việt `vi` / Tiếng Anh `en`) thông qua `ElConfigProvider` và composable `useThemeAndLocale`. Mọi nhãn hệ thống dùng chung cần hỗ trợ chuyển đổi mượt mà.
- **Khung Thông báo In-App (Notifications)**:
  - Nút chuông thông báo trên Header tích hợp popover hiển thị trạng thái và danh sách thông báo hệ thống (đồng bộ thiết bị, cảnh báo BLLĐ, đơn từ mới), có nút "Đánh dấu đã đọc" và empty state tinh tế.
- **Tiêu chuẩn Thẩm mỹ & Thân thiện Người dùng (UX & Enterprise Cleanliness)**:
  - **TUYỆT ĐỐI KHÔNG** hiển thị tên thư viện UI hay framework kỹ thuật (ví dụ: "Element Plus UI", "PrimeVue", "Vue 3", "Laravel Core") lên các banner hay tiêu đề giao diện người dùng cuối. Thay vào đó dùng các thông điệp nghiệp vụ chuyên nghiệp (ví dụ: *"Smart-HR Enterprise • Quản trị Nhân sự & Phân ca Thông minh"*).
  - Trạng thái bản quyền ở góc dưới thanh điều hướng (Sidebar Footer) phải hiển thị thân thiện, trang trọng với người dùng doanh nghiệp (biểu tượng bảo mật xanh lá, *"Bản quyền Doanh nghiệp"*, *"Bảo mật & Toàn vẹn CSDL"*), tránh dùng các chuỗi mã kỹ thuật thô ráp như *"Ed25519 Verified"*.
- **Tối ưu trải nghiệm**: Sử dụng `preserveScroll: true` và `preserveState: true` khi lọc dữ liệu hoặc đổi tab Bảng công chính thức / Tạm tính (Preview).

### 5.3 IoT Edge Collector (Golang)
- Tuân thủ chuẩn mực **Effective Go**, cấu trúc thư mục dạng `cmd/collector` và `internal/`.
- Xử lý lỗi tường minh (`if err != nil`), không nuốt lỗi.
- Quản lý Goroutines chặt chẽ qua `context.Context`, ngăn ngừa rò rỉ bộ nhớ.
- Đồng bộ mạng có Retry với Exponential Backoff và Circuit Breaker.

---

## 6. TIÊU CHUẨN GIAO TIẾP & TÀI LIỆU HÓA (COMMUNICATION & DOCS)

- **Ngôn ngữ phản hồi:** Luôn trả lời và giải thích bằng **Tiếng Việt** rõ ràng, gãy gọn. Giữ nguyên các thuật ngữ kỹ thuật chuyên ngành bằng tiếng Anh.
- **Phong cách tài liệu:** Khách quan, chuẩn xác, ngôn ngữ kỹ sư phần mềm chuyên nghiệp. **TUYỆT ĐỐI KHÔNG** sử dụng đại từ nhân xưng ("tôi", "chúng tôi", "chúng ta", "I", "we").
- **Tài liệu tham chiếu:** Toàn bộ kiến trúc và đặc tả chi tiết lưu trữ tại thư mục `docs/` (`docs/TECHNICAL_SPECIFICATION.md`). Mọi sửa đổi thiết kế lớn đều phải cập nhật lại tài liệu này.
