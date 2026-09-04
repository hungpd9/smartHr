# TÀI LIỆU KỸ THUẬT MODULE 11: ENTERPRISE LICENSING & CRYPTOGRAPHIC SECURITY ANCHORING (HỆ THỐNG BẢO VỆ BẢN QUYỀN & MÓC NEO MẬT MÃ)

> **Tên phân hệ:** `Licensing`  
> **Namespace Backend:** `App\Modules\Licensing` & `cmd/collector/license` (Golang)  
> **Trách nhiệm chính:** Bảo vệ bản quyền thương mại của phần mềm khi mã nguồn công khai trên GitHub thông qua cơ chế **Cặp bài trùng Đối xứng (Symmetric Coupled Pair) giữa Golang Edge Daemon và Laravel Web Core**, dùng chung **01 tệp bản quyền duy nhất (`smart-hr.lic`)** do tác giả cấp, kết hợp **Móc neo Mật mã CSDL (Cryptographic Data Anchoring)**, vận hành hoàn toàn ngoại tuyến (**Offline-First Verification**) mà không đòi hỏi tác giả phải duy trì máy chủ cấp phép 24/7.

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
   - Dữ liệu lương và CCCD nhân viên được mã hóa trong CSDL bằng thuật toán `AES-256-GCM`.
   - Khóa giải mã được dẫn xuất toán học từ chính file `smart-hr.lic` kết hợp với `Domain`. Nếu xóa code kiểm tra bản quyền, CSDL sẽ không thể giải mã, trả về lỗi hoặc dữ liệu rác.

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
    end

    LicFile -.->|Copy vào| LicCopy1
    LicFile -.->|Copy vào| LicCopy2

    GenConfig -->|Gửi Batch Quẹt thẻ kèm chữ ký:\nSignature = HMAC-SHA256(Payload, shared_hmac_secret)| API["API: POST /api/v1/attendance/sync"]
    API --> LaravelApp
    LaravelApp --> Verify{"Đối soát chữ ký HMAC\nvới shared_hmac_secret trong lic"}
    Verify -- Khớp --> Accept["✅ Chấp nhận log & Đưa vào Pipeline tính công"]
    Verify -- Không khớp --> Reject["❌ Từ chối gói tin (HTTP 401 Unauthorized)"]
```

---

## 3. Cơ chế Chống Sao chép Lậu (Anti-Theft & Anti-Leaking Mechanics)

Mô hình này loại bỏ hoàn toàn nguy cơ một bên thứ ba (Công ty B) sao chép phần mềm từ một khách hàng hợp lệ (Công ty A):

| Tình huống Gian lận của Công ty B | Phản ứng của Hệ thống | Kết quả |
|---|---|---|
| **1. Công ty B xin/chép trộm file `smart-hr-edge.exe` và file `smart-hr.lic` của Công ty A về dùng cho domain `hrm.company-b.com`** | Khi chạy Go Daemon lần đầu, wizard yêu cầu nhập URL hệ thống (`hrm.company-b.com`). Go dùng Public Key giải mã file `smart-hr.lic` và phát hiện `allowed_domain` là `hrm.company-a.com`. | **Go Daemon dừng ngay lập tức**: Báo lỗi sai tên miền bản quyền, không tạo file cấu hình, không kết nối máy chấm công. |
| **2. Công ty B cố tình nhập domain `hrm.company-a.com` vào Go Daemon để đánh lừa** | File Go chấp nhận vì khớp tên miền và kết nối với máy chấm công của Công ty B. Tuy nhiên, khi gửi log quẹt thẻ, Go sẽ gửi toàn bộ về máy chủ của... **Công ty A**. | **Vô dụng với Công ty B**: Dữ liệu công của Công ty B chạy sang hệ thống của Công ty A, máy chủ của Công ty B hoàn toàn không có dữ liệu. |
| **3. Công ty B tự clone Laravel từ GitHub về chạy, nhưng không có file `smart-hr.lic`** | Không có file license hợp lệ được ký bởi Private Key của tác giả, Laravel không thể lấy được `shared_hmac_secret`. | **Giao tiếp thất bại**: Khi Go gửi log lên, Laravel từ chối vì không xác thực được chữ ký HMAC. Đồng thời, CSDL không thể giải mã trường lương. |
| **4. Công ty B sửa file `smart-hr.lic` bằng Text Editor để đổi tên domain thành `hrm.company-b.com`** | Thuật toán chữ ký số Ed25519 kiểm tra hash toàn vẹn của payload. Chỉ cần thay đổi 1 ký tự trong file, chữ ký sẽ không khớp với Public Key. | **Chữ ký bị hỏng**: Cả Go và Laravel lập tức đánh dấu trạng thái `TAMPERED` và khóa toàn bộ chức năng. |

---

## 4. Trải nghiệm Khởi chạy Lần đầu của Golang Edge Daemon (First-Run Wizard)

File `smart-hr-edge.exe` hoạt động cực kỳ trực quan và chuyên nghiệp:

```text
======================================================================
               SMART-HR EDGE COLLECTOR - SETUP WIZARD
======================================================================
[KIỂM TRA GIẤY PHÉP]
  ✓ Đã tìm thấy tệp bản quyền: smart-hr.lic
  ✓ Chữ ký số tác giả: HỢP LỆ (Verified by Ed25519)
  ✓ Đơn vị sở hữu: Công ty TNHH Bán lẻ May Mặc Thời Trang
  ✓ Tên miền được phép: hrm.fashion-retail.vn
  ✓ Thời hạn sử dụng: Đến hết ngày 31/12/2027

----------------------------------------------------------------------
[CẤU HÌNH KẾT NỐI HỆ THỐNG]
> Nhập URL máy chủ Smart-HR của bạn: https://hrm.fashion-retail.vn
  ✓ Tên miền khớp hoàn toàn với giấy phép bản quyền!

> Nhập IP máy chấm công trong mạng LAN (vd: 192.168.1.201): 192.168.1.201
> Nhập Port kết nối (Mặc định 4370): 4370
> Loại thiết bị (1: ZKTeco, 2: Hikvision): 1

[KẾT NỐI THIẾT BỊ]
  ✓ Đang kết nối máy chấm công qua Socket UDP port 4370... THÀNH CÔNG!
  ✓ Nhận diện thiết bị: ZKTeco SenseFace 7A (Serial: ZK88992211)

----------------------------------------------------------------------
[HOÀN TẤT KHỞI TẠO]
  ✓ Đã tạo tệp cấu hình: config.json
  ✓ Đã ghép đôi thành công với máy chủ Cloud qua mã khóa đối xứng!
  ✓ Dịch vụ thu thập log đang hoạt động ngầm. Nhấn Ctrl+C để thoát.
======================================================================
```

---

## 5. Trụ cột Mật mã CSDL (Cryptographic Data Anchoring trong Laravel)

Song song với việc khóa chặt máy chấm công qua Go, Laravel Core sử dụng chính file `smart-hr.lic` để mở khóa dữ liệu nhạy cảm:

### 5.1 Dẫn xuất Khóa Giải mã Dữ liệu (DEK)
```php
namespace App\Modules\Licensing\Services;

class LicenseKeyDerivationService
{
    /**
     * Dẫn xuất Data Encryption Key (DEK) từ License Payload và Domain hiện tại
     */
    public function deriveDataEncryptionKey(string $licenseFilePath, string $currentDomain): string
    {
        // 1. Đọc và xác thực chữ ký số file license bằng Public Key
        $licensePayload = LicenseVerifier::verifyAndExtract($licenseFilePath);

        // 2. Kiểm tra domain hợp lệ
        if (!in_array($currentDomain, $licensePayload['allowed_domains'], true)) {
            throw new InvalidLicenseDomainException("Domain {$currentDomain} is not authorized.");
        }

        // 3. Dẫn xuất khóa DEK 256 bits bằng thuật toán HKDF (RFC 5869)
        $salt = config('app.key'); // Application Salt
        $ikm = $licensePayload['shared_hmac_secret'] . $currentDomain;

        return hash_hkdf('sha256', $ikm, 32, 'SmartHR-Data-Encryption-v1', $salt);
    }
}
```

### 5.2 Mã hóa Trường Dữ liệu Tự động (Eloquent Encrypted Casts)
Trong Model `EmployeeContract` và `PayrollRecord`:
```php
namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Licensing\Casts\LicenseEncryptedDecimal;

class EmployeeContract extends Model
{
    protected $casts = [
        // Tự động mã hóa khi lưu vào DB và giải mã khi đọc ra bằng khóa DEK
        'base_salary' => LicenseEncryptedDecimal::class,
        'insurance_salary' => LicenseEncryptedDecimal::class,
    ];
}
```
> Nếu ai đó xóa bỏ Middleware kiểm tra bản quyền trong Laravel, khóa $\text{DEK}$ sẽ không được khởi tạo $\to$ Mọi câu truy vấn vào `base_salary` sẽ giải mã thất bại và làm gián đoạn toàn bộ tính toán lương.

---

## 6. Lệnh CLI Dành Riêng cho Tác giả để Cấp Bản quyền

Tác giả chạy lệnh này trên máy tính cá nhân (không cần mở server public) để sinh file `smart-hr.lic` gửi cho khách hàng:

```bash
# Tác giả chạy trên máy local cá nhân:
php artisan license:generate \
    --customer="Công ty TNHH Bán lẻ May Mặc Thời Trang" \
    --domain="hrm.fashion-retail.vn" \
    --employees=200 \
    --branches=10 \
    --expires="2027-12-31" \
    --output="./dist/smart-hr.lic"
```

Tệp `smart-hr.lic` được tạo ra là một chuỗi Base64 chứa:
1. Payload thông tin cấp phép (JSON).
2. Chữ ký số mật mã Ed25519 (64 bytes).
3. Hạt giống bí mật `shared_hmac_secret` ngẫu nhiên để Go và Laravel bắt tay nhau.

---

## 7. Kịch bản Kiểm thử & Xác minh Tính Toàn vẹn (Test Scenarios)

1. **Test `GoWizardDomainMismatchRejection`**:
   - Chạy `smart-hr-edge.exe` với file `smart-hr.lic` đăng ký cho `hrm.company-a.com`.
   - Trong wizard, người dùng gõ domain `hrm.company-b.com`.
   - Kết quả kỳ vọng: Ứng dụng Go từ chối khởi chạy, không tạo `config.json`.
2. **Test `SymmetricHMACVerificationSuccess`**:
   - Go Daemon đẩy lô quẹt thẻ lên `/api/v1/attendance/sync` có chữ ký HMAC được sinh từ `shared_hmac_secret`.
   - Laravel đọc cùng file `smart-hr.lic` và đối soát chữ ký thành công $\to$ Ghi nhận quẹt thẻ vào CSDL.
3. **Test `DataDecryptionFailureOnLicenseAbsence`**:
   - Xóa file `smart-hr.lic` khỏi thư mục `storage/license/`.
   - Truy vấn hợp đồng lao động của nhân viên để tính lương.
   - Kết quả kỳ vọng: Ném ngoại lệ `MissingLicenseException`, toàn bộ lương không thể đọc được dưới dạng plaintext.
