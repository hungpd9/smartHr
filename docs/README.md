# SMART-HR: HỆ THỐNG TÀI LIỆU KỸ THUẬT MODULE CHUẨN HÓA (11 MODULES)

> **Hệ sinh thái Quản trị Nguồn nhân lực Thông minh, Đa Chi nhánh & Tuân thủ Bộ luật Lao động Việt Nam 2019**  
> *Kiến trúc: Modular Monolith (Laravel 13+ / Vue 3 + Inertia.js + PrimeVue / Golang Edge IoT / MySQL 8.0)*

---

## 📌 Tổng quan Hệ thống Tài liệu

Toàn bộ logic nghiệp vụ, mô hình dữ liệu, công thức giải thuật và quy chuẩn triển khai của dự án **Smart-HR** được phân tách thành 11 tài liệu kỹ thuật chuyên sâu đại diện cho 11 Bounded Contexts độc lập. Đây là "xương sống" kiến trúc bất biến giúp đội ngũ phát triển luôn có định hướng rõ ràng, tránh nhầm lẫn và dễ dàng mở rộng.

---

## 📂 Mục lục 11 Module Nghiệp vụ & Dịch vụ Nền tảng

| Module | Tệp Tài liệu Kỹ thuật | Trách nhiệm Nghiệp vụ Cốt lõi | Căn cứ Pháp luật & Điểm nhấn |
|:---:|---|---|---|
| **01** | [Module 01: Core HR & Career Timeline](modules/01_core_hr.md) | Quản lý Đa pháp nhân/Chi nhánh, Sơ đồ phòng ban có lineage đổi tên/chức năng, Hồ sơ số, Cây timeline nghề nghiệp (Effective Dating) và Hợp đồng lao động. | Truy vấn điểm thời gian (Point-in-time query), không làm sai lệch báo cáo quá khứ khi cơ cấu tổ chức thay đổi. |
| **02** | [Module 02: Policy Engine](modules/02_policy_engine.md) | Trung tâm Cấu hình Quy chế Động (Zero-Hardcode): Nấc phạt muộn/sớm theo phút & tần suất, Ma trận phúc lợi con nhỏ/gia đình, Biểu thuế TNCN 5 bậc mới. | Triệt tiêu hoàn toàn hardcode trong mã nguồn. Luật đổi hoặc công ty đổi quy chế chỉ cần ban hành `policy_version` mới. |
| **03** | [Module 03: Time & Attendance](modules/03_attendance.md) | Thu thập log từ Edge Daemon (Go) có HMAC-SHA256, Bảng log thô bất biến, Pipeline tính công 7 Pipe, Động cơ xem trước bảng công tạm tính (`TimesheetPreviewService`), Khóa tháng. | Khắc phục tắc nghẽn nộp đơn của hệ thống cũ; hỗ trợ xem bảng công Tạm tính song song với bảng công Đã duyệt. |
| **04** | [Module 04: Shift Planning & Rostering](modules/04_rostering.md) | Quản lý ca mẫu (Hành chính, Ca xoay, Ca gãy FnB, Ca đêm), Lịch phân ca, Động cơ Solver phân ca tự động thỏa mãn các ràng buộc pháp luật, Đổi ca (Swap) & Nhận ca thay (Cover). | **BLLĐ 2019**: Nghỉ 12h giữa ca (Đ.110), 24h nghỉ tuần (Đ.111), trần 48h/tuần (Đ.105), cấm ca đêm cho phụ nữ mang thai/nuôi con nhỏ (Đ.137). |
| **05** | [Module 05: Leave & Overtime Policy](modules/05_leave_policy.md) | Quỹ phép năm tự động (12 ngày + 1 ngày thâm niên mỗi 5 năm), Chuyển phép tồn (Carry-over), Đổi OT sang nghỉ bù (`compensation_rate`), Nghỉ việc riêng có lương (kết hôn, tang chế), Giờ nghỉ phụ nữ. | **Điều 113, 114, 115, 137 BLLĐ 2019**: Tự động hóa tính phép thâm niên và kiểm soát trần làm thêm giờ ngày 50%, tháng 40h, năm 200h/300h. |
| **06** | [Module 06: Dynamic Workflow & Approval](modules/06_workflow.md) | Biểu mẫu động JSON Schema, Định tuyến duyệt đa cấp (Cây tổ chức `Hierarchical` hoặc Nhóm chỉ định `Sequential/Parallel`), Snapshot người duyệt tại thời điểm nộp đơn, Tự duyệt đơn (Self-approve), Ủy quyền duyệt. | Bảo toàn thẩm quyền người duyệt lịch sử khi cơ cấu nhân sự thay đổi; hỗ trợ đơn sửa công (`request_timesheet_edits`). |
| **07** | [Module 07: Payroll & Statutory Gateway](modules/07_payroll.md) | Kết nối bảng công đã khóa, Tính lương Gross-to-Net, Bóc tách OT miễn thuế với thuật toán 3 tầng trần, Trích nộp BHXH (10.5% NLĐ / 21.5% NSDLĐ), Biểu thuế TNCN 5 bậc hiệu lực từ 2026, Phiếu lương số. | Thông tư 111/2013/TT-BTC, Nghị định 145/2020/NĐ-CP, Luật Thuế TNCN sửa đổi 2026. Kiểm soát chặt chẽ điều kiện chuyên cần và phụ cấp. |
| **08** | [Module 08: Compliance & Reporting](modules/08_compliance_reporting.md) | Giám sát tuân thủ thời gian thực (Cảnh báo vàng 35h / đỏ 40h OT tháng, Hết hạn HĐLĐ trước 30 ngày, Nguy cơ kiệt sức Burnout), Xuất báo cáo Streaming FastExcel/OpenSpout (RAM $< 64$MB), Audit Trail bất biến. | Bảo vệ doanh nghiệp trước rủi ro pháp lý và tranh chấp lao động; tối ưu hóa hiệu năng dữ liệu lớn cho chuỗi hàng chục nghìn nhân sự. |
| **09** | [Module 09: Notifications & Multi-Channel](modules/09_notifications.md) | Động cơ phân phối thông báo hướng đối tượng (Nhân viên, Chức vụ, Phòng ban, Chi nhánh, Vai trò), Chuông thông báo In-App Realtime (WebSockets/SSE), Email Queue thông minh, và **Adapter Driver mở rộng cho bên thứ 3 (Telegram, Zalo OA/ZNS, MS Teams, Slack)**. | Thiết kế sẵn sàng cắm rút Driver; thông báo đa ngôn ngữ; lưu vết nhật ký phân phối và Circuit Breaker chống gián đoạn. |
| **10** | [Module 10: Localization, Export & Document Engine](modules/10_localization_and_document_engine.md) | Nền tảng dịch vụ dùng chung: Hỗ trợ Đa ngôn ngữ (Localization / i18n Vi, En, Ja, Ko) cho giao diện và dữ liệu động, Động cơ Xuất/Nhập dữ liệu lớn Excel & CSV (Streaming I/O RAM $< 64$MB), và **Động cơ Sinh văn bản pháp lý PDF (Phiếu lương số bảo mật mã PIN, Hợp đồng lao động, Quyết định bổ nhiệm)**. | Hỗ trợ tiếng Việt Unicode không vỡ font; bảo mật phiếu lương điện tử; kế thừa và nâng cấp helper hàng đợi thông minh từ `timekeeper2`. |
| **11** | [Module 11: Licensing & Cryptographic Anchoring](modules/11_licensing_and_security_anchoring.md) | **Hệ thống Bảo vệ Bản quyền Thương mại & Móc neo Mật mã**: Móc neo khóa mật mã vào CSDL (Cryptographic Data Anchoring với HKDF & AES-256-GCM), Chốt chặn phần cứng biên Golang (Golang Edge Binary Anchor), Xác thực bất đối xứng ngoại tuyến (Offline Ed25519). | Bảo vệ bản quyền tối ưu cho cá nhân vận hành; không cần máy chủ xác thực 24/7; kẻ gian bẻ code PHP sẽ làm hỏng dữ liệu lương và không kết nối được máy chấm công. |

---

## 🛠️ Tài liệu Kỹ thuật Chung & Hướng dẫn Phát triển

1. 🏛️ [Tài liệu Thiết kế Kiến trúc Tổng thể (TECHNICAL_SPECIFICATION.md)](TECHNICAL_SPECIFICATION.md): Mô hình phân tầng tổng quan, Golang IoT Edge Daemon, và DDL Schema 21 bảng dữ liệu hoàn chỉnh.
2. 🤖 [Quy chuẩn Phát triển & Tiêu chuẩn Code cho AI/Dev (GEMINI.md)](../GEMINI.md): Chuẩn PSR-12, Effective Go, nguyên tắc Effective Dating và Zero-Hardcode Policy.
3. 📌 [Giới thiệu Dự án & Tech Stack (README.md)](../README.md): Tổng quan các đột phá công nghệ và sứ mệnh nền tảng Smart-HR.
