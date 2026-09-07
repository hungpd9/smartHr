<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Enums;

enum ReportType: string
{
    case TIMESHEET_SUMMARY = 'TIMESHEET_SUMMARY';
    case PAYROLL_ANALYSIS = 'PAYROLL_ANALYSIS';
    case HEADCOUNT_TURNOVER = 'HEADCOUNT_TURNOVER';
    case COMPLIANCE_AUDIT = 'COMPLIANCE_AUDIT';
    case CHILD_WELFARE = 'CHILD_WELFARE';

    public function label(): string
    {
        return match ($this) {
            self::TIMESHEET_SUMMARY => 'Bảng tổng hợp công tháng',
            self::PAYROLL_ANALYSIS => 'Bảng phân tích chi phí lương & thuế bảo hiểm',
            self::HEADCOUNT_TURNOVER => 'Báo cáo biến động nhân sự & cơ cấu lao động',
            self::COMPLIANCE_AUDIT => 'Báo cáo rà soát tuân thủ BLLĐ 2019',
            self::CHILD_WELFARE => 'Báo cáo phúc lợi con nhỏ & giảm trừ gia cảnh',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TIMESHEET_SUMMARY => 'Tổng hợp ngày công chuẩn, công thực tế, nghỉ phép, muộn/sớm và giờ làm thêm lũy tiến các loại (ngày, đêm, cuối tuần, lễ tết).',
            self::PAYROLL_ANALYSIS => 'Phân tích chi tiết quỹ lương Gross, phụ cấp, OT miễn thuế theo NĐ 145, trích nộp bảo hiểm 10.5% / 23.5% và thuế TNCN 5 bậc 2026.',
            self::HEADCOUNT_TURNOVER => 'Thống kê biến động nhân sự tuyển mới (Onboarding), thôi việc (Offboarding), phân bổ theo phòng ban, chi nhánh và loại hợp đồng.',
            self::COMPLIANCE_AUDIT => 'Rà soát các rủi ro pháp lý lao động: Cận trần/vượt trần OT 40h/tháng & 200h/năm, khoảng cách ca < 12h, ca đêm thai sản và HĐLĐ sắp hết hạn.',
            self::CHILD_WELFARE => 'Theo dõi danh sách con nhỏ dưới 12 tháng / dưới 6 tuổi, tình trạng người phụ thuộc giảm trừ gia cảnh và chế độ hỗ trợ doanh nghiệp.',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::TIMESHEET_SUMMARY => 'Chấm công',
            self::PAYROLL_ANALYSIS => 'Tiền lương',
            self::HEADCOUNT_TURNOVER => 'Nhân sự',
            self::COMPLIANCE_AUDIT => 'Pháp lý',
            self::CHILD_WELFARE => 'Phúc lợi',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, type?: string, align?: string, width?: int}>
     */
    public function columns(): array
    {
        return match ($this) {
            self::TIMESHEET_SUMMARY => [
                ['key' => 'user_code', 'label' => 'Mã NV', 'width' => 110],
                ['key' => 'employee_name', 'label' => 'Họ và tên', 'width' => 180],
                ['key' => 'department', 'label' => 'Phòng ban', 'width' => 170],
                ['key' => 'standard_days', 'label' => 'Công chuẩn', 'type' => 'number', 'align' => 'right', 'width' => 100],
                ['key' => 'actual_work_days', 'label' => 'Công thực tế', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'paid_leave_days', 'label' => 'Nghỉ phép', 'type' => 'number', 'align' => 'right', 'width' => 100],
                ['key' => 'unpaid_leave_days', 'label' => 'Nghỉ không lương', 'type' => 'number', 'align' => 'right', 'width' => 120],
                ['key' => 'late_minutes', 'label' => 'Đi muộn (phút)', 'type' => 'number', 'align' => 'right', 'width' => 120],
                ['key' => 'early_minutes', 'label' => 'Về sớm (phút)', 'type' => 'number', 'align' => 'right', 'width' => 120],
                ['key' => 'ot_normal_hours', 'label' => 'OT thường (h)', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'ot_night_hours', 'label' => 'OT đêm (h)', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'ot_weekend_hours', 'label' => 'OT cuối tuần (h)', 'type' => 'number', 'align' => 'right', 'width' => 130],
                ['key' => 'total_ot_hours', 'label' => 'Tổng giờ OT', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'submission_status', 'label' => 'Trạng thái chu trình', 'width' => 150],
            ],

            self::PAYROLL_ANALYSIS => [
                ['key' => 'user_code', 'label' => 'Mã NV', 'width' => 110],
                ['key' => 'employee_name', 'label' => 'Họ và tên', 'width' => 180],
                ['key' => 'department', 'label' => 'Phòng ban', 'width' => 160],
                ['key' => 'base_salary', 'label' => 'Lương cơ bản', 'type' => 'currency', 'align' => 'right', 'width' => 130],
                ['key' => 'gross_salary', 'label' => 'Tổng thu nhập Gross', 'type' => 'currency', 'align' => 'right', 'width' => 150],
                ['key' => 'ot_taxable', 'label' => 'Lương OT chịu thuế', 'type' => 'currency', 'align' => 'right', 'width' => 140],
                ['key' => 'ot_tax_exempt', 'label' => 'Lương OT miễn thuế (NĐ 145)', 'type' => 'currency', 'align' => 'right', 'width' => 170],
                ['key' => 'allowances_total', 'label' => 'Tổng phụ cấp', 'type' => 'currency', 'align' => 'right', 'width' => 130],
                ['key' => 'employee_insurance', 'label' => 'Bảo hiểm NLĐ (10.5%)', 'type' => 'currency', 'align' => 'right', 'width' => 150],
                ['key' => 'employer_cost', 'label' => 'Bảo hiểm NSDLĐ (23.5%)', 'type' => 'currency', 'align' => 'right', 'width' => 160],
                ['key' => 'pit_tax', 'label' => 'Thuế TNCN', 'type' => 'currency', 'align' => 'right', 'width' => 130],
                ['key' => 'net_salary', 'label' => 'Thực nhận (Net)', 'type' => 'currency', 'align' => 'right', 'width' => 140],
            ],

            self::HEADCOUNT_TURNOVER => [
                ['key' => 'department', 'label' => 'Khối / Phòng ban', 'width' => 200],
                ['key' => 'total_headcount', 'label' => 'Quy mô hiện tại', 'type' => 'number', 'align' => 'right', 'width' => 130],
                ['key' => 'official_count', 'label' => 'Chính thức', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'probation_count', 'label' => 'Thử việc', 'type' => 'number', 'align' => 'right', 'width' => 100],
                ['key' => 'onboard_count', 'label' => 'Tuyển mới', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'offboard_count', 'label' => 'Nghỉ việc', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'net_growth', 'label' => 'Tăng trưởng ròng', 'type' => 'number', 'align' => 'right', 'width' => 130],
                ['key' => 'turnover_rate', 'label' => 'Tỷ lệ nghỉ việc (%)', 'type' => 'percent', 'align' => 'right', 'width' => 140],
            ],

            self::COMPLIANCE_AUDIT => [
                ['key' => 'created_at', 'label' => 'Ngày phát hiện', 'width' => 120],
                ['key' => 'severity', 'label' => 'Mức độ', 'width' => 100],
                ['key' => 'alert_type_label', 'label' => 'Loại rủi ro', 'width' => 220],
                ['key' => 'user_code', 'label' => 'Mã NV', 'width' => 100],
                ['key' => 'employee_name', 'label' => 'Họ và tên', 'width' => 170],
                ['key' => 'department', 'label' => 'Phòng ban', 'width' => 160],
                ['key' => 'labor_code_ref', 'label' => 'Căn cứ BLLĐ 2019', 'width' => 190],
                ['key' => 'status', 'label' => 'Trạng thái khắc phục', 'width' => 150],
                ['key' => 'resolved_at', 'label' => 'Thời điểm giải quyết', 'width' => 150],
            ],

            self::CHILD_WELFARE => [
                ['key' => 'user_code', 'label' => 'Mã NV', 'width' => 110],
                ['key' => 'employee_name', 'label' => 'Họ tên nhân viên', 'width' => 180],
                ['key' => 'department', 'label' => 'Phòng ban', 'width' => 160],
                ['key' => 'child_name', 'label' => 'Họ tên con', 'width' => 180],
                ['key' => 'birth_date', 'label' => 'Ngày sinh', 'width' => 120],
                ['key' => 'age_months', 'label' => 'Tuổi (tháng)', 'type' => 'number', 'align' => 'right', 'width' => 110],
                ['key' => 'is_under_12m', 'label' => 'Con < 12 tháng (Đ.137)', 'width' => 160],
                ['key' => 'is_tax_dependent', 'label' => 'Giảm trừ gia cảnh', 'width' => 140],
                ['key' => 'tax_code', 'label' => 'Mã số thuế NPT', 'width' => 140],
                ['key' => 'monthly_allowance', 'label' => 'Trợ cấp hàng tháng', 'type' => 'currency', 'align' => 'right', 'width' => 150],
            ],
        };
    }
}
