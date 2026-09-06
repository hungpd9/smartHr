# TÀI LIỆU KỸ THUẬT MODULE 11: ENTERPRISE LICENSING & CRYPTOGRAPHIC SECURITY ANCHORING (HỆ THỐNG BẢO VỆ BẢN QUYỀN & MÓC NEO MẬT MÃ)

> **Tên phân hệ:** `Licensing`  
> **Namespace Backend:** `App\Modules\Licensing` & `cmd/collector/license` (Golang)  
> **Trách nhiệm chính:** Bảo vệ bản quyền thương mại của phần mềm khi mã nguồn công khai trên GitHub thông qua cơ chế **Cặp bài trùng Đối xứng (Symmetric Coupled Pair) giữa Golang Edge Daemon và Laravel Web Core**, dùng chung **01 tệp bản quyền duy nhất (`smart-hr.lic`)** do tác giả cấp, kết hợp **Móc neo Mật mã CSDL (Cryptographic Data Anchoring)** mã hóa trường CCCD và Lương, vận hành hoàn toàn ngoại tuyến (**Offline-First Verification**), đồng thời hiển thị trạng thái bản quyền thân thiện doanh nghiệp trên giao diện người dùng.

---

## 1. Ranh giới Nghiệp vụ & Triết lý Thiết kế (Domain Boundaries & Philosophy)

Khi mã nguồn Laravel được công khai trên GitHub phục vụ mục đích portfolio/tuyển dụng, khách hàng có thể clone về máy chủ riêng để tự triển khai. Các phương thức kiểm tra `if/else` bằng PHP truyền thống hoàn toàn vô hiệu vì bất kỳ lập trình viên nào cũng có thể sửa code thành `return true;`.

Module `Licensing` giải quyết triệt để vấn đề này bằng triết lý: **"Dùng một file nhị phân Golang chung (không thể sửa code) kết hợp cùng 01 file license do tác giả cấp để khóa chặt giao tiếp với Laravel Web và giải mã CSDL."**

### 1.1 Nguyên lý Vận hành Cốt lõi:
1. **01 File Nhị phân Go Duy nhất (`smart-hr-edge.exe`)**: Tác giả chỉ biên dịch duy nhất một bản Go nhị phân dùng chung cho mọi khách hàng, trong đó nhúng sẵn **Public Key** của tác giả.
2. **01 File Giấy phép Duy nhất (`smart-hr.lic`)**: Khi bán phần mềm cho khách hàng có tên miền `hrm.abc.com`, tác giả dùng **Private Key** trên máy tính cá nhân ký số sinh ra file `smart-hr.lic` chứa `allowed_domain` và hạt giống bí mật `shared_hmac_secret`.
3. **Cặp bài trùng Đối xứng (Go ⟷ Laravel)**:
   - Cả Go Daemon (tại máy tính chi nhánh) và Laravel Web (trên máy chủ cloud) đều dùng chung file `smart-hr.lic` này.
   - Go và Laravel chỉ có thể nói chuyện và trao đổi log quẹt thẻ khi cả hai có **cùng một license hợp lệ và cùng một domain đã được cấp phép**.
4. **Móc neo Khóa Mật mã CSDL (Cryptographic Data Anchoring)**:
   - Dữ liệu lương (`base_salary`) và CCCD nhân viên (`citizen_id`) được mã hóa trong CSDL bằng thuật toán `AES-256-GCM`.
   - Khóa giải mã được dẫn xuất toán học bằng hàm HKDF từ chính chữ ký số trong file `smart-hr.lic` kết hợp với `Domain`. Nếu xóa code kiểm tra bản quyền, CSDL sẽ không thể giải mã, trả về lỗi hoặc dữ liệu rác.
5. **Tiêu chuẩn Hiển thị Thân thiện Doanh nghiệp (Enterprise UI Presentation)**:
   - Ở góc dưới thanh điều hướng (Sidebar Footer), hệ thống hiển thị biểu tượng bảo mật màu xanh lá kèm nhãn trang trọng: *"Bản quyền Doanh nghiệp"*, *"Bảo mật & Toàn vẹn CSDL"*.
   - Tuyệt đối không hiển thị các chuỗi mã kỹ thuật thô ráp như *"Ed25519 Verified"* hay *"Binary Coupled Lock"*, bảo đảm tính chuyên nghiệp cao cấp cho người dùng cuối.

---

## 2. Kiến trúc Cặp Bài trùng Đối xứng (Golang Edge ⟷ Laravel Web Core)

### 2.1 Sơ đồ Dòng Dữ liệu và Bắt tay Xác thực Đối xứng

```mermaid
flowchart TD
    subgraph Author ["🔐 PHÍA TÁC GIẢ (CẤP BẢN QUYỀN)"]
        PrivKey["Private Key (Tác giả giữ bí mật trên máy cá nhân)"]
        CLI["CLI Nội bộ:\nphp artisan license:generate --domain=hrm.abc.com"]
        PrivKey --> CLI
        CLI --> LicFile["Tệp Bản quyền Duy nhất: smart-hr.lic\n(Ký số Ed25519, chứa allowed_domain & shared_hmac_secret)"]
    end

    subgraph Client_LAN ["🏢 TẠI CHI NHÁNH / MÁY THU NGÂN (GOLANG DAEMON)"]
        GoApp["smart-hr-edge.exe\n(File binary chung, nhúng sẵn Public Key)"]
        LicCopy1["smart-hr.lic (Đặt cùng thư mục với file Go)"]
        
        GoApp --> ReadLic1["Go đọc smart-hr.lic & xác thực bằng Public Key"]
        ReadLic1 --> Wizard["Lần đầu chạy: Wizard yêu cầu nhập:\n1. URL Web: https://hrm.abc.com\n2. IP máy chấm công: 192.168.1.201"]
        Wizard --> MatchDomain{"URL Web nhập vào ==\nallowed_domain trong lic?"}
        MatchDomain -- Sai / Không khớp --> Die["❌ Từ chối chạy!\nBáo lỗi: Domain không khớp với License"]
        MatchDomain -- Đúng --> GenConfig["Tạo config.json cục bộ & Bắt đầu thu thập"]
    end

    subgraph Client_Web ["☁️ TẠI MÁY CHỦ DOANH NGHIỆP (LARAVEL 13 CORE)"]
        LaravelApp["Laravel 13 Core\n(Nhúng sẵn Public Key)"]
        LicCopy2["smart-hr.lic (Đặt trong storage/license/)"]
        LaravelApp --> ReadLic2["Laravel đọc smart-hr.lic & lấy shared_hmac_secret"]
        LaravelApp --> Decrypt["Dẫn xuất DEK qua HKDF để giải mã Lương & CCCD"]
        LaravelApp --> UI["Hiển thị Sidebar: Bản quyền Doanh nghiệp (Bảo mật CSDL)"]
    end

    LicFile -.->|Copy vào| LicCopy1
    LicFile -.->|Copy vào| LicCopy2

    GenConfig -->|Gửi Batch Quẹt thẻ kèm chữ ký:\nSignature = HMAC-SHA256(Payload, shared_hmac_secret)| API["API: POST /api/v1/attendance/sync"]
    API --> VerifySig{"Laravel xác thực:\nHMAC == Signature?"}
    VerifySig -- Sai --> Reject["403 Forbidden: Giả mạo hoặc sai License"]
    VerifySig -- Đúng --> Success["200 OK: Tiếp nhận vào raw_attendance_logs"]
```

---

## 3. Quy trình Dẫn xuất Khóa Mật mã CSDL (Cryptographic Data Anchoring)

Khóa giải mã dữ liệu (`Data Encryption Key - DEK`) được sinh ra tự động:
$$
\text{DEK} = \text{HKDF-SHA256}\Big(\text{Salt} = \text{Domain},\; \text{IKM} = \text{Ed25519\_Signature},\; \text{Info} = \text{"SmartHR\_DEK\_v1"}\Big)
$$
1. Khi đọc CCCD hoặc mức lương cơ bản: Hệ thống dùng `DEK` giải mã ciphertext từ cột `citizen_id` và `base_salary`.
2. Nếu mã nguồn PHP bị chỉnh sửa để bypass kiểm tra license: Biến `Ed25519_Signature` bị rỗng $\to$ `DEK` sai $\to$ Không thể giải mã dữ liệu $\to$ Toàn bộ hệ thống tính lương và hồ sơ nhân sự bị vô hiệu hóa.

---

## 4. Kịch bản Kiểm thử Tích hợp (Test Scenarios)

1. **Test `ValidLicenseDomainDecryption`**:
   - Nạp file `smart-hr.lic` hợp lệ với domain `smart-hr.test`.
   - Thực hiện đọc trường `citizen_id` và `base_salary`.
   - Kết quả kỳ vọng: Dữ liệu CCCD và lương được giải mã hiển thị chính xác.
2. **Test `SidebarLicenseFriendlyDisplay`**:
   - Đăng nhập vào giao diện web với tài khoản bất kỳ.
   - Kiểm tra Sidebar Footer.
   - Kết quả kỳ vọng: Hiển thị biểu tượng khiên bảo mật xanh lá, dòng chữ *"Bản quyền Doanh nghiệp"*, *"Bảo mật & Toàn vẹn CSDL"*, không chứa từ khóa *"Ed25519"*.
