# TÀI LIỆU KỸ THUẬT MODULE 10: LOCALIZATION, EXPORT & DOCUMENT GENERATION ENGINE (ĐA NGÔN NGỮ, XUẤT NHẬP DỮ LIỆU & SINH TÀI LIỆU PDF)

> **Tên phân hệ:** `PlatformSupport`  
> **Namespace Backend:** `App\Modules\PlatformSupport`  
> **Trách nhiệm chính:** Cung cấp hạ tầng dịch vụ dùng chung (Shared Services) cho toàn hệ thống: Hỗ trợ đa ngôn ngữ (Localization / i18n) cho cả giao diện và dữ liệu động, bộ động cơ xuất nhập dữ liệu lớn Excel/CSV bằng kỹ thuật Streaming I/O (RAM $< 64$MB) hỗ trợ xuất hồ sơ con nhỏ, danh mục phụ cấp, và động cơ sinh tài liệu văn bản pháp lý định dạng PDF (Phiếu lương số bảo mật PIN, Hợp đồng lao động, Quyết định thôi việc/chốt thâm niên).

---

## 1. Ranh giới Nghiệp vụ & Trách nhiệm (Domain Boundaries)

Module `PlatformSupport` đóng vai trò là "Xưởng công cụ dùng chung" (Shared Utility & Presentation Foundation):
1. **Hệ thống Đa ngôn ngữ Toàn diện (Comprehensive Localization / i18n)**:
   - Hỗ trợ đầy đủ các ngôn ngữ thông dụng trong doanh nghiệp tại Việt Nam: **Tiếng Việt (`vi`)** (mặc định), **Tiếng Anh (`en`)**, **Tiếng Nhật (`ja`)**, **Tiếng Hàn (`ko`)** thông qua `ElConfigProvider` và composable `useThemeAndLocale`.
   - *Dịch giao diện tĩnh (Static UI Translations)*: Quản lý qua tệp tin JSON hoặc PHP Array trong thư mục `lang/{locale}/`.
   - *Đa ngôn ngữ dữ liệu động (Dynamic Content Localization)*: Áp dụng Trait `HasTranslations` cho các Model có thuộc tính đa ngữ (Tên phòng ban, Tên phiên bản quy chế, Mô tả chế độ phúc lợi).
2. **Đồng bộ Giao diện Sáng / Tối (Dark & Light Mode)**:
   - Import trực tiếp `element-plus/theme-chalk/dark/css-vars.css`, mọi component (Table, Dialog, Card, Input, Tag) tự động tương thích hoàn toàn khi chuyển đổi dark mode.
   - Lưu vết trạng thái theme trong `localStorage` và duy trì xuyên suốt các phiên làm việc.
3. **Động cơ Xuất / Nhập Dữ liệu Lớn Excel & CSV (Streaming I/O Engine)**:
   - Sử dụng thư viện **FastExcel / OpenSpout** ghi trực tiếp từ con trỏ CSDL (`lazyById()`, `cursor()`).
   - Duy trì mức tiêu thụ RAM luôn dưới **64MB** khi xuất:
     - Bảng tổng hợp công tháng & chi tiết ngày công.
     - Bảng tổng hợp lương & chi tiết thuế/bảo hiểm.
     - Danh sách con nhỏ & đăng ký giảm trừ gia cảnh (`exportChildrenList`).
     - Bảng danh mục phụ cấp & chế độ phúc lợi doanh nghiệp (`exportAllowancesAndWelfare`).
4. **Động cơ Sinh Tài liệu Pháp lý PDF (PDF Document Generation Engine)**:
   - Tạo các văn bản pháp lý chuẩn in ấn có hỗ trợ đầy đủ Unicode tiếng Việt:
     - **Phiếu lương điện tử (Payslip PDF)**: Mã hóa mật khẩu xem bằng 4 số cuối CCCD/Ngày sinh của nhân viên.
     - **Hợp đồng lao động mẫu (Labor Contract PDF)**: Tự động điền mức lương cơ bản và phụ cấp.
     - **Quyết định Nhân sự & Thôi việc (Offboard Decision PDF)**: Tự động đính kèm số năm, tháng, ngày thâm niên làm việc chính xác được tính từ `CareerTimelineService`.

---

## 2. Thiết kế Cơ sở Dữ liệu & Bổ sung Thuộc tính (Database Schema)

### 2.1 Bổ sung Trường Ngôn ngữ & Template Tài liệu

```sql
-- Bảng quản lý template văn bản PDF (Mẫu hợp đồng, Quyết định thôi việc, Phiếu lương)
CREATE TABLE `document_templates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL COMMENT 'Mã template: CONTRACT_STANDARD, DECISION_OFFBOARDING, PAYSLIP_PIN',
    `name` VARCHAR(255) NOT NULL,
    `document_type` ENUM('CONTRACT', 'DECISION', 'PAYSLIP', 'TIMESHEET_REPORT') NOT NULL,
    `blade_view_path` VARCHAR(255) NOT NULL,
    `header_html` TEXT NULL,
    `footer_html` TEXT NULL,
    `watermark_text` VARCHAR(100) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    UNIQUE KEY `uk_tenant_doc_code` (`tenant_id`, `code`),
    CONSTRAINT `fk_doc_template_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Kiến trúc Xuất Dữ liệu Lớn Streaming I/O

```php
namespace App\Modules\PlatformSupport\Services;

use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use App\Modules\CoreHR\Models\EmployeeChild;

class StreamingChildrenExportService
{
    public function exportChildrenList(int $tenantId, string $outputPath): void
    {
        $writer = new Writer();
        $writer->openToFile($outputPath);

        // Header
        $writer->addRow(Row::fromValues([
            'Mã NV', 'Họ tên nhân viên', 'Họ tên con', 'Ngày sinh', 'Tuổi (tháng)', 'Giảm trừ gia cảnh', 'Mã số thuế NPT', 'Trợ cấp con nhỏ hàng tháng'
        ]));

        EmployeeChild::where('tenant_id', $tenantId)
            ->with(['employee'])
            ->lazyById(500)
            ->each(function ($child) use ($writer) {
                $ageMonths = $child->birth_date ? now()->diffInMonths($child->birth_date) : 0;
                $writer->addRow(Row::fromValues([
                    $child->employee->user_code,
                    $child->employee->full_name,
                    $child->full_name,
                    $child->birth_date?->format('d/m/Y'),
                    $ageMonths,
                    $child->is_tax_dependent ? 'Có' : 'Không',
                    $child->tax_dependent_code ?? '---',
                    number_format((float)$child->monthly_allowance_amount, 0, ',', '.') . ' đ',
                ]));
            });

        $writer->close();
    }
}
```

---

## 4. Kịch bản Kiểm thử Tích hợp (Test Scenarios)

1. **Test `ThemeDarkModeCssVarsLoaded`**:
   - Chuyển đổi Dark/Light mode trên giao diện.
   - Kết quả kỳ vọng: Biến CSS `element-plus/theme-chalk/dark/css-vars.css` được nạp, toàn bộ bảng Table và Card đổi màu nền tối hoàn chỉnh, lưu `smart_hr_theme = dark` vào `localStorage`.
2. **Test `StreamingExportMemorySafetyUnder64MB`**:
   - Giả lập 20.000 bản ghi con nhỏ và phụ cấp.
   - Xuất file qua `StreamingChildrenExportService`.
   - Kết quả kỳ vọng: File Excel được tạo đầy đủ dữ liệu, RAM đỉnh tiêu thụ của tiến trình PHP $< 45$MB.
