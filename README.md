# Smart-HR

> **Hệ sinh thái Quản trị Nguồn nhân lực Thông minh, Đa Chi nhánh & Tuân thủ Pháp luật Lao động Việt Nam**  
> *Next-Generation Intelligent HRMS & Rostering Platform (Laravel 13 / Vue 3 + Inertia.js / Golang Edge IoT)*

---

## 📌 Giới thiệu dự án

**Smart-HR** là nền tảng quản trị nhân sự (HRMS) thế hệ mới được thiết kế chuyên biệt cho các doanh nghiệp quy mô chuỗi (Chuỗi bán lẻ - Retail Chains, Chuỗi kho bãi logistics - Warehouse Chains) cũng như các doanh nghiệp hiện đại. 

Dự án kế thừa các bài học thực chiến quý giá từ hệ thống tiền nhiệm (`timekeeper2`), giải quyết triệt để các vấn đề kiến trúc cũ và mở rộng thành một hệ thống quản trị nguồn nhân lực khoa học, linh hoạt, dễ tích hợp và bám sát tuyệt đối **Bộ luật Lao động Việt Nam 2019 (BLLĐ 2019)** cùng các chính sách thuế, bảo hiểm xã hội mới nhất (bao gồm biểu thuế TNCN 5 bậc áp dụng từ 2026).

---

## 🌟 Điểm nhấn Kiến trúc & Tính năng Đột phá

### 1. Mô hình Lịch sử Nhân sự & Phòng ban dạng Timeline (Effective Dating)
- **Truy vết biến động tổ chức**: Lưu trữ phiên bản phòng ban (`department_versions`) với đầy đủ lịch sử đổi tên, thay đổi chức năng nhiệm vụ, chuyển giao người quản lý theo từng giai đoạn `[effective_date, end_date]`.
- **Cây Timeline Nghề nghiệp Nhân sự (`employee_career_timelines`)**: Tái hiện toàn bộ hành trình của nhân viên từ ngày vào công ty (Onboarding), các quyết định điều chuyển chi nhánh/phòng ban (Transfer), bổ nhiệm chức vụ (Promotion), nâng bậc lương đến khi nghỉ việc (Offboarding). Dễ dàng truy vấn trạng thái nhân sự tại bất kỳ thời điểm nào trong quá khứ mà không làm sai lệch báo cáo lịch sử.

### 2. Trung tâm Cấu hình Quy chế Động (Zero-Hardcode Policy Engine)
- **Không hardcode quy chế & luật**: Toàn bộ quy định về chấm công, phạt đi muộn/về sớm, hệ số làm thêm giờ, thời gian nghỉ giữa các ca, trần giờ làm việc đều được quản lý tập trung theo các phiên bản quy chế có ngày hiệu lực (`policy_versions`).
- **Thích ứng khi luật đổi**: Khi Nhà nước ban hành Nghị định mới hoặc Doanh nghiệp đổi nội quy, chỉ cần tạo một phiên bản quy chế mới. Dữ liệu công và lương của các tháng trước được bảo toàn nguyên vẹn.

### 3. Cơ chế Phúc lợi Con nhỏ & Gia đình Linh hoạt (Dynamic Family Care Matrix)
- **Tuân thủ chuẩn luật (Điều 137 BLLĐ 2019)**: Tự động giảm 1 giờ làm việc hưởng nguyên lương cho lao động nữ nuôi con dưới 12 tháng. Mặc định cấm ca đêm ($22:00 - 06:00$) và làm thêm giờ, **TRỪ TRƯỜNG HỢP người lao động có đơn thỏa thuận/đồng thuận tự nguyện** (kèm quy trình ký cam kết và cảnh báo Approver trước khi duyệt).
- **Phúc lợi riêng mở rộng**: Doanh nghiệp tự do thiết lập các chế độ phúc lợi riêng (ví dụ: chế độ con từ 1-11 tuổi được miễn trừ 60 phút không lương, hoặc con 1-6 tuổi về sớm 30 phút...), hỗ trợ bật/tắt hoặc tùy biến nấc tuổi linh hoạt.

### 4. Động cơ Tính công Lũy tiến & Bảng công Tạm tính (Timesheet Preview)
- **Pipeline tính công**: Áp dụng 3 tầng lũy tiến (Lũy tiến OT 1.5x/2.0x/3.0x, Lũy tiến phạt muộn/sớm theo số phút vi phạm, Lũy tiến phạt theo tần suất tái phạm trong tháng).
- **Tách bạch Bảng công Tạm tính & Đã duyệt**: Giải phóng tắc nghẽn luồng phê duyệt từ phiên bản cũ. Nhân viên có thể nộp đơn nghỉ phép/OT bất kỳ lúc nào; hệ thống cung cấp tab "Tạm tính" hiển thị kết quả nếu các đơn chờ được duyệt song song với tab "Đã duyệt" chính thức.

### 5. Động cơ Phân ca Thông minh (Auto Rostering Constraint Solver)
- **Bảo đảm 100% Ràng buộc Cứng của Pháp luật**: Tối thiểu 12 giờ nghỉ giữa 2 ca làm việc liên tiếp (Điều 110 BLLĐ), tối thiểu 24 giờ nghỉ liên tục mỗi tuần (Điều 111 BLLĐ), tối đa 48h làm chuẩn/tuần.
- **Tối ưu hóa theo 2 mục tiêu**: Chế độ *Công bằng (Fairness)* cân bằng ca đêm/cuối tuần hoặc Chế độ *Tối ưu chi phí (Cost Optimization)* hạn chế OT giờ cao điểm.

### 6. Động cơ Tính Lương (Payroll Engine) & Biểu thuế TNCN 5 Bậc mới
- Kết nối tự động với bảng công tháng đã khóa (`monthly_attendances`).
- Thuật toán bóc tách tiền làm thêm giờ (OT) miễn thuế với 3 tầng trần (ngày 50%, tháng 40h, năm 200h/300h).
- Tích hợp biểu thuế TNCN 5 bậc lũy tiến động có hiệu lực từ 2026 theo luật sửa đổi, bóc tách phụ cấp chịu thuế/miễn thuế và trích nộp bảo hiểm chuẩn xác (BHXH, BHYT, BHTN).

### 7. Bảo vệ Bản quyền Đối xứng (Symmetric Licensing: Go ⟷ Laravel)
- File Go nhị phân chung và Laravel Web dùng chung 01 tệp `smart-hr.lic` có chữ ký số Ed25519 của tác giả.
- Móc neo khóa mật mã CSDL (Cryptographic Data Anchoring): Trường lương và CCCD được mã hóa bằng AES-256-GCM dẫn xuất từ License + Domain; kẻ gian bẻ code PHP sẽ làm hỏng dữ liệu lương và không kết nối được máy chấm công.

### 8. IoT Edge Collector Daemon (Golang)
- Ứng dụng daemon độc lập viết bằng Go, hoạt động trong mạng LAN của từng chi nhánh/kho.
- Kết nối trực tiếp máy chấm công ZKTeco (Socket UDP/TCP 4370) và Hikvision (HTTP ISAPI / Push ADMS).
- Bộ đệm cục bộ SQLite (Offline-First) bảo toàn dữ liệu khi mất kết nối Internet, tự động đồng bộ theo lô có chữ ký HMAC-SHA256 lên Cloud.

---

## 🛠️ Công nghệ Sử dụng (Tech Stack)

| Phân hệ | Công nghệ | Vai trò |
|---|---|---|
| **Core Backend** | **Laravel 13 / PHP 8.4+** | Application Core, Modular Architecture, Pipeline Engine, RESTful API |
| **Frontend UI** | **Vue 3 + Inertia.js + Element Plus** | Single-Page Experience (Server-Driven SPA), Reactive Timesheet Grid, Drag-and-Drop Rostering, TailwindCSS, 100% MIT License mã nguồn mở |
| **IoT Edge Daemon** | **Golang (Go 1.26+)** | Kết nối máy chấm công LAN, SQLite Offline buffer, Cloud Sync HMAC |
| **Cơ sở dữ liệu** | **MySQL 8.0+** | Multi-Tenant Partitioning, Temporal Effective Dating, Immutable Audit Trail |
| **Hàng đợi & Đệm** | **Redis / Laravel Horizon** | Asynchronous Queue, Distributed Lock, Cache Policy |
| **Xuất Báo cáo & PDF** | **FastExcel / OpenSpout & DomPDF** | Streaming I/O xuất Excel RAM $< 64$MB & Sinh phiếu lương/HĐLĐ PDF |

---

## 📂 Cấu trúc Tài liệu Kỹ thuật Dự án

- 📚 [Hệ thống 11 Tài liệu Kỹ thuật Module Chuẩn hóa (docs/README.md)](docs/README.md): Danh mục chi tiết 11 Bounded Contexts của hệ thống.
- 📖 [Tài liệu Thiết kế Kỹ thuật Toàn diện (TECHNICAL_SPECIFICATION.md)](docs/TECHNICAL_SPECIFICATION.md): Đặc tả chi tiết kiến trúc phân tầng, lược đồ cơ sở dữ liệu MySQL DDL 21 bảng, giải thuật tính công lũy tiến, động cơ phân ca tự động CSP, workflow duyệt và kiến trúc Frontend.
- 🤖 [Hướng dẫn Quy chuẩn cho AI Agent (GEMINI.md)](GEMINI.md): Quy tắc làm việc, tiêu chuẩn code (PSR-12, Effective Go, Vue 3 Composition API), quy trình Plan/Act và môi trường thực thi.
