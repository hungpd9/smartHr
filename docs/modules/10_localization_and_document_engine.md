# TÀI LIỆU KỸ THUẬT MODULE 10: LOCALIZATION, EXPORT & DOCUMENT GENERATION ENGINE (ĐA NGÔN NGỮ, XUẤT NHẬP DỮ LIỆU & SINH TÀI LIỆU PDF)

> **Tên phân hệ:** `PlatformSupport`  
> **Namespace Backend:** `App\Modules\PlatformSupport`  
> **Trách nhiệm chính:** Cung cấp hạ tầng dịch vụ dùng chung (Shared Services) cho toàn hệ thống: Hỗ trợ đa ngôn ngữ (Localization / i18n) cho cả giao diện và dữ liệu động, bộ động cơ xuất nhập dữ liệu lớn Excel/CSV bằng kỹ thuật Streaming I/O (RAM $< 64$MB), và động cơ sinh tài liệu văn bản pháp lý định dạng PDF (Phiếu lương số, Hợp đồng lao động, Quyết định bổ nhiệm).

---

## 1. Ranh giới Nghiệp vụ & Trách nhiệm (Domain Boundaries)

Module `PlatformSupport` đóng vai trò là "Xưởng công cụ dùng chung" (Shared Utility & Presentation Foundation):
1. **Hệ thống Đa ngôn ngữ Toàn diện (Comprehensive Localization / i18n)**:
   - Hỗ trợ đầy đủ các ngôn ngữ thông dụng trong doanh nghiệp tại Việt Nam: **Tiếng Việt (`vi`)** (mặc định), **Tiếng Anh (`en`)**, **Tiếng Nhật (`ja`)**, **Tiếng Hàn (`ko`)**.
   - *Dịch giao diện tĩnh (Static UI Translations)*: Quản lý qua tệp tin JSON hoặc PHP Array trong thư mục `lang/{locale}/` (`auth.php`, `attendance.php`, `payroll.php`, `validation.php`).
   - *Đa ngôn ngữ dữ liệu động (Dynamic Content Localization)*: Áp dụng Trait `HasTranslations` cho các Model có thuộc tính đa ngữ (Tên phòng ban, Tên phiên bản quy chế, Mô tả chức vụ) lưu dưới dạng JSON.
   - *Định dạng số liệu & ngày tháng bản địa hóa*: Tự động định dạng tiền tệ (VND, USD, JPY), phân tách hàng nghìn (chấm/phẩy), định dạng ngày tháng (`d/m/Y` theo chuẩn VN hoặc `Y-m-d`).
   - *Cơ chế xác định ngôn ngữ*: Nhận diện theo thứ tự ưu tiên: Cấu hình cá nhân (`employees.preferred_locale`) $\to$ Session $\to$ Header `Accept-Language` $\to$ Cấu hình mặc định của Tenant.
2. **Động cơ Xuất / Nhập Dữ liệu Lớn Excel & CSV (Streaming I/O Engine)**:
   - Sử dụng thư viện **FastExcel / OpenSpout** ghi trực tiếp từ con trỏ CSDL (`lazyById()`, `cursor()`) vào tệp tạm.
   - Bảo đảm mức tiêu thụ RAM luôn duy trì dưới **64MB** khi xuất bảng công chi tiết, bảng lương, hoặc nhật ký quẹt thẻ thô lên đến hàng trăm nghìn dòng.
   - Cung cấp động cơ Import theo lô (Batch Import) kèm cơ chế kiểm tra tính hợp lệ (Validation) từng dòng, trả về báo cáo lỗi chi tiết nếu có dòng dữ liệu sai định dạng.
3. **Động cơ Sinh Tài liệu Pháp lý PDF (PDF Document Generation Engine)**:
   - Tạo các văn bản pháp lý chuẩn in ấn có hỗ trợ đầy đủ Unicode tiếng Việt (phông chữ Noto Sans / Roboto / DejaVu Sans):
     - **Phiếu lương điện tử (Payslip PDF)**: Có thể mã hóa đặt mật khẩu xem bằng 4 số cuối CCCD/Ngày sinh của nhân viên.
     - **Hợp đồng lao động mẫu (Labor Contract PDF)**: Tự động điền thông tin nhân viên, điều khoản lương và đại diện doanh nghiệp theo đúng quy chuẩn BLLĐ 2019.
     - **Quyết định Nhân sự (Decision PDF)**: Quyết định bổ nhiệm, điều chuyển phòng ban, thôi việc kèm số quyết định và chức danh người ký.
     - **Bảng tổng hợp công tháng**: Mẫu biểu chuẩn có ô ký xác nhận của Quản lý chi nhánh.
4. **Trợ lý Quản lý Hàng đợi Thông minh (Smart Queue Helper)**:
   - Kế thừa và nâng cấp từ bài học thực chiến của `timekeeper2` (`QUEUE_MAIL_HELPER.md`): Tự động phát hiện Worker đang hoạt động, điều phối Job vào queue hoặc fallback gửi an toàn, cung cấp lệnh Artisan quản lý tiến trình queue.

---

## 2. Thiết kế Cơ sở Dữ liệu & Bổ sung Thuộc tính (Database Schema)

### 2.1 Bổ sung Trường Ngôn ngữ & Dữ liệu Đa ngữ

```sql
-- Bổ sung trường ngôn ngữ ưu tiên vào bảng employees
ALTER TABLE `employees` 
ADD COLUMN `preferred_locale` VARCHAR(10) DEFAULT 'vi' COMMENT 'Ngôn ngữ ưa thích: vi, en, ja, ko',
ADD COLUMN `timezone` VARCHAR(50) DEFAULT 'Asia/Ho_Chi_Minh';

-- Bảng quản lý template văn bản PDF (Mẫu hợp đồng, Quyết định bổ nhiệm)
CREATE TABLE `document_templates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL COMMENT 'Mã template: CONTRACT_PROBATION, DECISION_PROMOTION, PAYSLIP_STANDARD',
    `name` VARCHAR(255) NOT NULL,
    `document_type` ENUM('CONTRACT', 'DECISION', 'PAYSLIP', 'TIMESHEET_REPORT') NOT NULL,
    `blade_view_path` VARCHAR(255) NOT NULL COMMENT 'Đường dẫn Blade template: documents.contracts.probation',
    `header_html` TEXT NULL,
    `footer_html` TEXT NULL,
    `watermark_text` VARCHAR(100) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `uk_tenant_doc_code` (`tenant_id`, `code`),
    CONSTRAINT `fk_doc_template_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng lưu vết các tệp tài liệu đã xuất (Export Job History)
CREATE TABLE `export_jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `export_type` ENUM('TIMESHEET_EXCEL', 'PAYROLL_EXCEL', 'PUNCH_LOGS_CSV', 'PAYSLIP_PDF_ZIP') NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `file_size_bytes` BIGINT UNSIGNED NULL,
    `total_rows` INT DEFAULT 0,
    `status` ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED') DEFAULT 'PENDING',
    `error_message` TEXT NULL,
    `expires_at` TIMESTAMP NULL COMMENT 'Thời hạn lưu tệp tải về (VD: sau 7 ngày tự xóa)',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Kiến trúc Đa ngôn ngữ (Localization Architecture)

### 3.1 Cấu trúc Thư mục Ngôn ngữ
```
lang/
├── vi/
│   ├── auth.php
│   ├── attendance.php
│   ├── leave.php
│   ├── payroll.php
│   ├── validation.php
│   └── documents.php
├── en/
│   ├── auth.php
│   ├── attendance.php
│   ├── leave.php
│   ├── payroll.php
│   ├── validation.php
│   └── documents.php
└── ja/
    ├── auth.php
    └── ...
```

### 3.2 Trait Xử lý Thuộc tính Đa ngữ Động (`HasTranslations`)
Cho phép lưu trữ dữ liệu đa ngôn ngữ trực tiếp trong các cột JSON của MySQL (ví dụ: `name_translations`):
```php
namespace App\Modules\PlatformSupport\Traits;

trait HasTranslations
{
    public function getTranslation(string $attribute, ?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();
        $translations = $this->{$attribute . '_translations'} ?? [];

        if (is_string($translations)) {
            $translations = json_decode($translations, true) ?? [];
        }

        return $translations[$locale] 
            ?? $translations[config('app.fallback_locale')] 
            ?? $this->{$attribute};
    }
}
```

---

## 4. Động cơ Sinh Tài liệu PDF (PDF Generation Engine)

### 4.1 Bộ điều phối Sinh PDF Chuẩn hóa (`PdfDocumentService`)

```php
namespace App\Modules\PlatformSupport\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfDocumentService
{
    /**
     * Xuất Phiếu lương số PDF kèm tùy chọn mã hóa mật khẩu
     */
    public function generatePayslipPdf(PayrollRecord $payrollRecord, ?string $passwordPin = null): \Barryvdh\DomPDF\PDF
    {
        $data = [
            'record' => $payrollRecord,
            'employee' => $payrollRecord->employee,
            'tenant' => $payrollRecord->employee->tenant,
            'locale' => $payrollRecord->employee->preferred_locale ?? 'vi',
        ];

        $pdf = Pdf::loadView('documents.payslip', $data)
            ->setPaper('a5', 'landscape')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        // Đặt mật khẩu bảo mật xem phiếu lương nếu có yêu cầu
        if ($passwordPin) {
            $pdf->setEncryption($passwordPin, $passwordPin, ['print', 'copy']);
        }

        return $pdf;
    }

    /**
     * Xuất Hợp đồng Lao động PDF chuẩn Bộ luật Lao động 2019
     */
    public function generateContractPdf(EmployeeContract $contract): \Barryvdh\DomPDF\PDF
    {
        $data = [
            'contract' => $contract,
            'employee' => $contract->employee,
            'tenant' => $contract->employee->tenant,
        ];

        return Pdf::loadView('documents.contracts.standard_vn', $data)
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans']);
    }
}
```

---

## 5. Động cơ Nhập / Xuất Excel & CSV Streaming

### 5.1 Kiến trúc Xuất Dữ liệu Lớn không Tràn RAM
```php
namespace App\Modules\PlatformSupport\Services;

use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;

class StreamingExcelService
{
    /**
     * Xuất dữ liệu lớn với RAM < 64MB bằng OpenSpout
     */
    public function streamToFile(string $filePath, array $headers, iterable $dataGenerator): void
    {
        $writer = new Writer();
        $writer->openToFile($filePath);

        // Định dạng Header
        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1E3A8A');

        $writer->addRow(Row::fromValues($headers, $headerStyle));

        // Nạp và ghi từng dòng dữ liệu tuần tự
        foreach ($dataGenerator as $recordValues) {
            $writer->addRow(Row::fromValues($recordValues));
        }

        $writer->close();
    }
}
```

---

## 6. Danh mục RESTful API Endpoints

| Method | URI | Quyền hạn | Mô tả |
|---|---|---|---|
| `POST` | `/api/v1/locale` | `public` | Chuyển đổi ngôn ngữ hiển thị giao diện (Session / Cookie) |
| `PUT` | `/api/v1/employees/me/locale` | `user.self` | Cập nhật ngôn ngữ và múi giờ ưa thích của nhân viên |
| `POST` | `/api/v1/documents/payslip/{recordId}/pdf` | `payroll.view` | Xuất phiếu lương cá nhân định dạng PDF (Tải về trực tiếp) |
| `POST` | `/api/v1/documents/contract/{contractId}/pdf` | `hr.contract.view` | Xuất hợp đồng lao động PDF chuẩn in ấn |
| `POST` | `/api/v1/documents/decision/{timelineId}/pdf` | `hr.emp.view` | Xuất quyết định bổ nhiệm/điều chuyển PDF |
| `POST` | `/api/v1/export/timesheet` | `att.export` | Tạo Job bất đồng bộ xuất bảng công chi tiết Excel Streaming |
| `GET` | `/api/v1/export/jobs/{id}` | `user.self` | Kiểm tra tiến độ Job xuất dữ liệu và lấy link tải về |

---

## 7. Kịch bản Kiểm thử Tích hợp (Test Scenarios)

1. **Test `LanguageSwitchingPersistence`**:
   - Nhân viên cập nhật `preferred_locale = 'en'`.
   - Gọi API lấy phiếu lương hoặc thông báo chuông.
   - Kết quả: Toàn bộ tiêu đề và nội dung trả về ngôn ngữ Tiếng Anh.
2. **Test `VietnameseUnicodePdfRendering`**:
   - Xuất hợp đồng lao động của nhân viên tên "Nguyễn Đắc Thắng", chức vụ "Trưởng phòng Tài chính - Kế toán".
   - Kết quả: File PDF hiển thị tiếng Việt dấu đầy đủ, phông chữ DejaVu Sans chuẩn, không bị vỡ/lỗi font (`tofu`).
3. **Test `ExportJobStreamingEfficiency`**:
   - Xuất bảng kê log chấm công thô của 20 chi nhánh trong 1 tháng (120.000 dòng log).
   - Kết quả: Tệp Excel tạo thành công trong 18 giây, RAM tiêu thụ tối đa của tiến trình PHP $< 48$MB.
