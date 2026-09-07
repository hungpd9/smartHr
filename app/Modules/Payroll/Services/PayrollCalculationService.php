<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Services\AuditTrailService;
use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\PolicyEngine\Models\PayrollPolicyConfig;
use App\Modules\PolicyEngine\Models\PitTaxBracket;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollCalculationService
{
    public function __construct(
        protected PolicyResolutionService $policyResolver,
        protected TemporalLockService $temporalLockService,
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Tính lương hàng tháng cho toàn bộ nhân viên thuộc Tenant.
     * Quy tắc bắt buộc: Nếu nhân viên nào chưa được phê duyệt bảng chấm công (is_approved = false),
     * tự động BỎ QUA không tính lương cho nhân viên đó.
     *
     * @return array{
     *     success: bool,
     *     month_year: string,
     *     calculated_count: int,
     *     skipped_count: int,
     *     calculated_employees: array,
     *     skipped_employees: array
     * }
     */
    public function calculateForMonth(int $tenantId, string $monthYear): array
    {
        // 1. Kiểm tra kỳ lương có bị khóa không
        $this->temporalLockService->assertPayrollNotLocked($tenantId, $monthYear);

        $date = Carbon::createFromFormat('Y-m', $monthYear)->startOfMonth();
        $startOfMonth = $date->copy()->startOfMonth()->toDateString();
        $endOfMonth = $date->copy()->endOfMonth()->toDateString();

        // 2. Tìm hoặc tạo bản ghi PayrollPeriod
        $payrollPeriod = PayrollPeriod::firstOrCreate(
            ['tenant_id' => $tenantId, 'month_year' => $monthYear],
            ['status' => 'DRAFT', 'is_locked' => false]
        );

        if ($payrollPeriod->is_locked) {
            throw new InvalidArgumentException("Kỳ lương {$monthYear} đã được chốt và khóa sổ. Không thể tính toán lại.");
        }

        // 3. Phân giải PolicyVersion hiệu lực tại thời điểm tính lương
        $policyVersion = $this->policyResolver->resolveForDate($tenantId, $date);
        if ($policyVersion) {
            $policyVersion->loadMissing(['payrollConfig', 'pitTaxBrackets']);
            $payrollPeriod->update(['policy_version_id' => $policyVersion->id]);
        }

        $payrollConfig = $policyVersion?->payrollConfig;

        // 4. Lấy biểu thuế TNCN 5 bậc động từ PolicyVersion (hoặc fallback chuẩn)
        $taxBrackets = $this->getTaxBrackets($policyVersion);

        // 5. Đọc danh sách nhân viên đang làm việc trong Tenant
        $employees = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['OFFICIAL', 'PROBATION', 'ON_LEAVE'])
            ->with(['children', 'certificates', 'salaries'])
            ->get();

        $calculatedEmployees = [];
        $skippedEmployees = [];

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                // Kiểm tra xem bảng chấm công tháng của nhân viên đã được phê duyệt chưa
                $monthlyAttendance = MonthlyAttendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('month_year', $monthYear)
                    ->first();

                // NGUYÊN TẮC CỐT LÕI: Chưa được phê duyệt bảng chấm công -> Bỏ qua không tính lương
                if (!$monthlyAttendance || !$monthlyAttendance->is_approved) {
                    $skippedEmployees[] = [
                        'employee_id' => $employee->id,
                        'user_code' => $employee->user_code,
                        'full_name' => $employee->full_name,
                        'status' => $employee->status,
                        'reason' => !$monthlyAttendance
                            ? 'Chưa có bảng chấm công tháng'
                            : 'Bảng chấm công chưa được phê duyệt (is_approved = false)',
                    ];
                    continue;
                }

                // 6. Tính toán chi tiết các cấu phần lương khi đã được duyệt công
                $record = $this->computeEmployeePayroll(
                    $tenantId,
                    $employee,
                    $monthlyAttendance,
                    $monthYear,
                    $policyVersion,
                    $payrollConfig,
                    $taxBrackets,
                    $startOfMonth,
                    $endOfMonth,
                    $date->copy()->endOfMonth()
                );

                $calculatedEmployees[] = [
                    'employee_id' => $employee->id,
                    'user_code' => $employee->user_code,
                    'full_name' => $employee->full_name,
                    'gross_salary' => $record->gross_salary,
                    'net_salary' => $record->net_salary,
                    'pit_tax' => $record->pit_tax_amount,
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'month_year' => $monthYear,
            'calculated_count' => count($calculatedEmployees),
            'skipped_count' => count($skippedEmployees),
            'calculated_employees' => $calculatedEmployees,
            'skipped_employees' => $skippedEmployees,
        ];
    }

    /**
     * Tính toán chi tiết các khoản lương, phụ cấp, bảo hiểm, thuế TNCN và lương Net của 1 nhân viên
     */
    protected function computeEmployeePayroll(
        int $tenantId,
        Employee $employee,
        MonthlyAttendance $attendance,
        string $monthYear,
        ?PolicyVersion $policyVersion,
        ?PayrollPolicyConfig $payrollConfig,
        array $taxBrackets,
        string $startOfMonth,
        string $endOfMonth,
        Carbon $targetDate
    ): PayrollRecord {
        // 1. Mức lương cơ bản thỏa thuận (Effective Dating - Không hardcode)
        // Tra cứu mức lương tại mốc thời gian này từ bảng employee_salaries
        $contractBaseSalary = $employee->getBaseSalaryAt($targetDate, 10000000.00);

        if ($employee->status === 'PROBATION') {
            // Điều 26 BLLĐ 2019: Lương thử việc ít nhất bằng 85% lương chính thức (lấy từ cấu hình động)
            $probationPercent = (float) ($payrollConfig?->probation_salary_percentage ?? 85.00);
            $contractBaseSalary = $contractBaseSalary * ($probationPercent / 100.0);
        }

        // 2. Ngày công chuẩn trong tháng (lấy từ cấu hình động, mặc định 22)
        $standardWorkDays = (float) ($payrollConfig?->fixed_standard_working_days ?? 22.00);
        $actualDays = (float) $attendance->total_standard_days;
        $basePayEarned = ($standardWorkDays > 0) ? ($contractBaseSalary / $standardWorkDays) * $actualDays : 0.00;

        // 3. Phụ cấp chức vụ từ Timeline nghề nghiệp (Effective Dating)
        $latestTimeline = EmployeeCareerTimeline::where('employee_id', $employee->id)
            ->where('effective_date', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startOfMonth);
            })
            ->with('position')
            ->latest('effective_date')
            ->first();

        $positionAllowance = 0.00;
        if ($latestTimeline && $latestTimeline->position) {
            $positionAllowance = (float) $latestTimeline->position->allowance_amount;
        }

        // 4. Trợ cấp con nhỏ (tổng từ danh sách con hoặc mức mặc định từ policy)
        $childAllowance = 0.00;
        $defaultChildAllowanceRate = (float) ($payrollConfig?->default_child_allowance ?? 300000.00);

        if ($employee->relationLoaded('children')) {
            foreach ($employee->children as $child) {
                if ($child->is_active) {
                    if ($child->has_monthly_allowance && (float) $child->monthly_allowance_amount > 0) {
                        $childAllowance += (float) $child->monthly_allowance_amount;
                    } elseif ($defaultChildAllowanceRate > 0) {
                        $childAllowance += $defaultChildAllowanceRate;
                    }
                }
            }
        }

        // 5. Phụ cấp chứng chỉ chuyên môn
        $certificateAllowance = 0.00;
        if ($employee->relationLoaded('certificates')) {
            $certificateAllowance = (float) $employee->certificates
                ->where('is_verified', true)
                ->sum('monthly_allowance');
        }

        // 6. Tiền phạt vi phạm chấm công
        $penaltyAmount = (float) $attendance->total_penalty_amount;

        // 7. Bóc tách làm thêm giờ (OT) theo Điều 98 BLLĐ 2019 & Nghị định 145/2020/NĐ-CP
        $hourlyRate = ($standardWorkDays > 0) ? ($contractBaseSalary / ($standardWorkDays * 8.0)) : 0.00;

        // Lấy chi tiết số giờ theo từng loại ngày từ daily_attendances trong tháng
        $dailyOt = DailyAttendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('
                SUM(ot_day_hours) as ot_day,
                SUM(ot_night_hours) as ot_night,
                SUM(ot_weekend_hours) as ot_weekend,
                SUM(ot_holiday_hours) as ot_holiday
            ')
            ->first();

        $otDayHours = (float) ($dailyOt?->ot_day ?? 0.0);
        $otNightHours = (float) ($dailyOt?->ot_night ?? 0.0);
        $otWeekendHours = (float) ($dailyOt?->ot_weekend ?? 0.0);
        $otHolidayHours = (float) ($dailyOt?->ot_holiday ?? 0.0);

        // Fallback: Nếu không có bản ghi chi tiết ngày mà monthly có total_ot_hours
        $totalRecordedOtHours = $otDayHours + $otNightHours + $otWeekendHours + $otHolidayHours;
        if ($totalRecordedOtHours <= 0 && (float) $attendance->total_ot_hours > 0) {
            $otDayHours = (float) $attendance->total_ot_hours;
        }

        // Các hệ số lương làm thêm giờ (Zero-Hardcode Policy)
        $rateWeekdayDay = (float) ($payrollConfig?->ot_weekday_day_rate ?? 1.50);
        $rateWeekdayNight = (float) ($payrollConfig?->ot_weekday_night_rate ?? 2.00);
        $rateWeekendDay = (float) ($payrollConfig?->ot_weekend_day_rate ?? 2.00);
        $rateHolidayDay = (float) ($payrollConfig?->ot_holiday_day_rate ?? 3.00);

        // Tiền OT từng loại
        $payOtDay = $otDayHours * $hourlyRate * $rateWeekdayDay;
        $payOtNight = $otNightHours * $hourlyRate * $rateWeekdayNight;
        $payOtWeekend = $otWeekendHours * $hourlyRate * $rateWeekendDay;
        $payOtHoliday = $otHolidayHours * $hourlyRate * $rateHolidayDay;
        $totalOtPay = $payOtDay + $payOtNight + $payOtWeekend + $payOtHoliday;

        // Phần trả cao hơn mức làm việc bình thường được MIỄN THUẾ TNCN theo Điều 98 BLLĐ
        $exemptOtDay = $otDayHours * $hourlyRate * max(0.0, $rateWeekdayDay - 1.0);
        $exemptOtNight = $otNightHours * $hourlyRate * max(0.0, $rateWeekdayNight - 1.0);
        $exemptOtWeekend = $otWeekendHours * $hourlyRate * max(0.0, $rateWeekendDay - 1.0);
        $exemptOtHoliday = $otHolidayHours * $hourlyRate * max(0.0, $rateHolidayDay - 1.0);
        $taxExemptOt = $exemptOtDay + $exemptOtNight + $exemptOtWeekend + $exemptOtHoliday;
        $taxableOt = max(0.0, $totalOtPay - $taxExemptOt);

        // 8. Tổng thu nhập Gross
        $grossSalary = max(0.00, $basePayEarned + $positionAllowance + $childAllowance + $certificateAllowance + $totalOtPay - $penaltyAmount);

        // 9. Khấu trừ Bảo hiểm bắt buộc (Zero-Hardcode Policy)
        $maxSalaryBhxh = (float) ($payrollConfig?->max_salary_bhxh ?? 46800000.00);
        $maxSalaryBhtn = (float) ($payrollConfig?->max_salary_bhtn ?? 99200000.00);

        $empBhxhRate = (float) ($payrollConfig?->employee_bhxh_rate ?? 8.00);
        $empBhytRate = (float) ($payrollConfig?->employee_bhyt_rate ?? 1.50);
        $empBhtnRate = (float) ($payrollConfig?->employee_bhtn_rate ?? 1.00);

        $compBhxhRate = (float) ($payrollConfig?->company_bhxh_rate ?? 17.50);
        $compBhytRate = (float) ($payrollConfig?->company_bhyt_rate ?? 3.00);
        $compBhtnRate = (float) ($payrollConfig?->company_bhtn_rate ?? 1.00);

        // Căn cứ đóng bảo hiểm: lấy lương cơ bản, kiểm soát theo trần 20 lần lương cơ sở
        $insuranceBase = $contractBaseSalary;
        $bhxhSalaryBase = min($insuranceBase, $maxSalaryBhxh);
        $bhtnSalaryBase = min($insuranceBase, $maxSalaryBhtn);

        // Nhân sự thử việc không trích nộp bảo hiểm bắt buộc theo luật
        $isOfficial = ($employee->status === 'OFFICIAL');
        $socialInsurance = $isOfficial ? round($bhxhSalaryBase * ($empBhxhRate / 100.0), 2) : 0.00;
        $healthInsurance = $isOfficial ? round($bhxhSalaryBase * ($empBhytRate / 100.0), 2) : 0.00;
        $unemploymentInsurance = $isOfficial ? round($bhtnSalaryBase * ($empBhtnRate / 100.0), 2) : 0.00;
        $totalInsurance = $socialInsurance + $healthInsurance + $unemploymentInsurance;

        // Chi phí Doanh nghiệp (NSDLĐ) chi trả thêm
        $companyBhxh = $isOfficial ? round($bhxhSalaryBase * ($compBhxhRate / 100.0), 2) : 0.00;
        $companyBhyt = $isOfficial ? round($bhxhSalaryBase * ($compBhytRate / 100.0), 2) : 0.00;
        $companyBhtn = $isOfficial ? round($bhtnSalaryBase * ($compBhtnRate / 100.0), 2) : 0.00;
        $companyUnion = $isOfficial ? round($bhxhSalaryBase * 0.02, 2) : 0.00; // KPCĐ 2%
        $totalCompanyContributions = $companyBhxh + $companyBhyt + $companyBhtn + $companyUnion;

        // 10. Tính Thuế TNCN
        $pitTaxAmount = 0.00;
        $assessableIncome = 0.00;
        $personalDeduction = 0.00;
        $totalDependentDeduction = 0.00;

        if ($employee->status === 'PROBATION') {
            // Lao động thử việc / vãng lai: Khấu trừ thuế toàn phần 10% nếu thu nhập từ 2,000,000 VND trở lên
            $probationPitRate = (float) ($payrollConfig?->probation_pit_rate ?? 10.00);
            $probationThreshold = (float) ($payrollConfig?->probation_pit_threshold ?? 2000000.00);

            if ($grossSalary >= $probationThreshold) {
                $taxableProbationIncome = max(0.00, $grossSalary - $taxExemptOt);
                $pitTaxAmount = round($taxableProbationIncome * ($probationPitRate / 100.0), 2);
            }
        } else {
            // Giảm trừ gia cảnh theo cấu hình chính sách
            $personalDeduction = (float) ($payrollConfig?->personal_deduction ?? 11000000.00);
            $dependentDeductionPerPerson = (float) ($payrollConfig?->dependent_deduction ?? 4400000.00);
            $dependentsCount = (int) $employee->tax_dependents_count;
            $totalDependentDeduction = $dependentsCount * $dependentDeductionPerPerson;

            // Thu nhập tính thuế = Thu nhập chịu thuế - Các khoản bảo hiểm - Giảm trừ gia cảnh
            $taxableIncome = max(0.00, $grossSalary - $taxExemptOt);
            $assessableIncome = max(0.00, $taxableIncome - $totalInsurance - $personalDeduction - $totalDependentDeduction);

            // Tính thuế lũy tiến theo các bậc động
            $pitTaxAmount = $this->calculateProgressiveTax($assessableIncome, $taxBrackets);
        }

        // 11. Lương thực lĩnh (Net Salary)
        $netSalary = max(0.00, $grossSalary - $totalInsurance - $pitTaxAmount);

        // 12. Đóng gói chi tiết phiếu lương (Salary Breakdown)
        $salaryBreakdown = [
            'earnings' => [
                'contract_base_salary' => round($contractBaseSalary, 2),
                'standard_work_days' => $standardWorkDays,
                'actual_work_days' => $actualDays,
                'base_pay_earned' => round($basePayEarned, 2),
                'hourly_rate' => round($hourlyRate, 2),
                'position_allowance' => round($positionAllowance, 2),
                'child_allowance' => round($childAllowance, 2),
                'certificate_allowance' => round($certificateAllowance, 2),
                'total_ot_pay' => round($totalOtPay, 2),
                'ot_details' => [
                    'day_hours' => $otDayHours,
                    'day_pay' => round($payOtDay, 2),
                    'day_exempt' => round($exemptOtDay, 2),
                    'night_hours' => $otNightHours,
                    'night_pay' => round($payOtNight, 2),
                    'night_exempt' => round($exemptOtNight, 2),
                    'weekend_hours' => $otWeekendHours,
                    'weekend_pay' => round($payOtWeekend, 2),
                    'weekend_exempt' => round($exemptOtWeekend, 2),
                    'holiday_hours' => $otHolidayHours,
                    'holiday_pay' => round($payOtHoliday, 2),
                    'holiday_exempt' => round($exemptOtHoliday, 2),
                ],
                'penalty_amount' => round($penaltyAmount, 2),
                'gross_salary' => round($grossSalary, 2),
            ],
            'deductions' => [
                'tax_exempt_ot' => round($taxExemptOt, 2),
                'taxable_ot' => round($taxableOt, 2),
                'taxable_income' => round(max(0.0, $grossSalary - $taxExemptOt), 2),
                'employee_insurance' => [
                    'bhxh' => round($socialInsurance, 2),
                    'bhyt' => round($healthInsurance, 2),
                    'bhtn' => round($unemploymentInsurance, 2),
                    'total' => round($totalInsurance, 2),
                ],
                'family_deduction' => [
                    'personal' => round($personalDeduction, 2),
                    'dependents_count' => (int) $employee->tax_dependents_count,
                    'dependents_amount' => round($totalDependentDeduction, 2),
                    'total' => round($personalDeduction + $totalDependentDeduction, 2),
                ],
                'assessable_income' => round($assessableIncome, 2),
                'pit_tax' => round($pitTaxAmount, 2),
            ],
            'net_salary' => round($netSalary, 2),
            'company_contributions' => [
                'bhxh' => round($companyBhxh, 2),
                'bhyt' => round($companyBhyt, 2),
                'bhtn' => round($companyBhtn, 2),
                'union_fee' => round($companyUnion, 2),
                'total' => round($totalCompanyContributions, 2),
            ],
            'policy_info' => [
                'policy_version_id' => $policyVersion?->id,
                'policy_version_name' => $policyVersion?->name ?? 'Mặc định',
            ],
        ];

        // 13. Lưu hoặc cập nhật PayrollRecord
        return PayrollRecord::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'month_year' => $monthYear,
            ],
            [
                'gross_salary' => round($grossSalary, 2),
                'position_allowance_amount' => round($positionAllowance, 2),
                'child_allowance_amount' => round($childAllowance, 2),
                'certificate_allowance_amount' => round($certificateAllowance, 2),
                'tax_exempt_ot_amount' => round($taxExemptOt, 2),
                'taxable_ot_amount' => round($taxableOt, 2),
                'social_insurance_employee' => round($socialInsurance, 2),
                'health_insurance_employee' => round($healthInsurance, 2),
                'unemployment_insurance_employee' => round($unemploymentInsurance, 2),
                'pit_tax_amount' => round($pitTaxAmount, 2),
                'net_salary' => round($netSalary, 2),
                'salary_breakdown' => $salaryBreakdown,
                'status' => 'DRAFT',
            ]
        );
    }

    /**
     * Tính thuế TNCN lũy tiến động theo danh sách nấc thuế
     */
    protected function calculateProgressiveTax(float $income, array $brackets): float
    {
        if ($income <= 0) {
            return 0.00;
        }

        $tax = 0.00;
        foreach ($brackets as $b) {
            $from = (float) ($b['income_from'] ?? 0);
            $to = isset($b['income_to']) && $b['income_to'] !== null ? (float) $b['income_to'] : INF;
            $rate = (float) ($b['tax_rate'] ?? 0);

            if ($income > $from) {
                $taxableInBracket = min($income, $to) - $from;
                $tax += $taxableInBracket * ($rate / 100);
            }
        }

        return round($tax, 2);
    }

    /**
     * Lấy danh sách bậc thuế TNCN từ PolicyVersion hoặc mặc định 5 bậc hiệu lực từ 2026
     */
    protected function getTaxBrackets(?PolicyVersion $policyVersion): array
    {
        if ($policyVersion) {
            $brackets = PitTaxBracket::where('policy_version_id', $policyVersion->id)
                ->orderBy('bracket_order', 'asc')
                ->get();

            if ($brackets->isNotEmpty()) {
                return $brackets->toArray();
            }
        }

        // Biểu thuế TNCN 5 bậc hiệu lực từ 2026 mặc định
        return [
            ['bracket_order' => 1, 'income_from' => 0, 'income_to' => 5000000, 'tax_rate' => 5],
            ['bracket_order' => 2, 'income_from' => 5000000, 'income_to' => 15000000, 'tax_rate' => 10],
            ['bracket_order' => 3, 'income_from' => 15000000, 'income_to' => 30000000, 'tax_rate' => 20],
            ['bracket_order' => 4, 'income_from' => 30000000, 'income_to' => 50000000, 'tax_rate' => 30],
            ['bracket_order' => 5, 'income_from' => 50000000, 'income_to' => null, 'tax_rate' => 35],
        ];
    }
}
