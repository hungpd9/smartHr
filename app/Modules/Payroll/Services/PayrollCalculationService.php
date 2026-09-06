<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Services\AuditTrailService;
use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\PayrollRecord;
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
            $payrollPeriod->update(['policy_version_id' => $policyVersion->id]);
        }

        // 4. Lấy biểu thuế TNCN 5 bậc động từ PolicyVersion (hoặc fallback chuẩn)
        $taxBrackets = $this->getTaxBrackets($policyVersion);

        // 5. Đọc danh sách nhân viên đang làm việc trong Tenant
        $employees = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['OFFICIAL', 'PROBATION', 'ON_LEAVE'])
            ->with(['children', 'certificates'])
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
                    $taxBrackets,
                    $startOfMonth,
                    $endOfMonth
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
        array $taxBrackets,
        string $startOfMonth,
        string $endOfMonth
    ): PayrollRecord {
        // Phụ cấp chức vụ từ Timeline nghề nghiệp (Effective Dating)
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

        // Trợ cấp con nhỏ (tổng từ danh sách con có trợ cấp)
        $childAllowance = (float) $employee->children
            ->where('is_active', true)
            ->where('has_monthly_allowance', true)
            ->sum('monthly_allowance_amount');

        // Phụ cấp chứng chỉ chuyên môn
        $certificateAllowance = (float) $employee->certificates
            ->where('is_verified', true)
            ->sum('monthly_allowance');

        // Mức lương cơ bản thỏa thuận (mặc định 10,000,000 nếu không có hợp đồng)
        $contractBaseSalary = 10000000.00;
        if ($employee->status === 'PROBATION') {
            // Điều 26 BLLĐ 2019: Lương thử việc ít nhất bằng 85% lương chính thức
            $contractBaseSalary = $contractBaseSalary * 0.85;
        }

        // Ngày công chuẩn trong tháng (mặc định 22 hoặc 26 ngày)
        $standardWorkDays = 22.0;
        $actualDays = (float) $attendance->total_standard_days;
        $basePayEarned = ($standardWorkDays > 0) ? ($contractBaseSalary / $standardWorkDays) * $actualDays : 0.00;

        // Tiền phạt vi phạm chấm công (muộn/sớm/tần suất)
        $penaltyAmount = (float) $attendance->total_penalty_amount;

        // Tính tiền làm thêm giờ (OT) bóc tách 3 tầng trần NĐ 145/2020
        $otHours = (float) $attendance->total_ot_hours;
        $hourlyRate = $contractBaseSalary / ($standardWorkDays * 8);
        $totalOtPay = $otHours * $hourlyRate * 1.5; // Hệ số OT 1.5x
        $taxExemptOt = $otHours * $hourlyRate * 0.5; // Phần trả cao hơn (50%) được miễn thuế TNCN
        $taxableOt = $totalOtPay - $taxExemptOt;     // Phần tính thuế bình thường

        // Tổng thu nhập trước khấu trừ (Gross Salary)
        $grossSalary = max(0.00, $basePayEarned + $positionAllowance + $childAllowance + $certificateAllowance + $totalOtPay - $penaltyAmount);

        // Khấu trừ Bảo hiểm bắt buộc NLĐ (BHXH 8%, BHYT 1.5%, BHTN 1%)
        // Tuân thủ trần đóng BHXH (tối đa 20 lần mức lương cơ sở = 46,800,000 VND)
        $bhxhSalaryBase = min($grossSalary, 46800000.00);
        $socialInsurance = $employee->status === 'OFFICIAL' ? ($bhxhSalaryBase * 0.08) : 0.00;
        $healthInsurance = $employee->status === 'OFFICIAL' ? ($bhxhSalaryBase * 0.015) : 0.00;
        $unemploymentInsurance = $employee->status === 'OFFICIAL' ? ($bhxhSalaryBase * 0.01) : 0.00;
        $totalInsurance = $socialInsurance + $healthInsurance + $unemploymentInsurance;

        // Tính thuế TNCN
        $pitTaxAmount = 0.00;
        if ($employee->status === 'PROBATION') {
            // Đối với thử việc/vãng lai: Thuế toàn phần 10% nếu thu nhập từ 2,000,000 VND trở lên
            if ($grossSalary >= 2000000.00) {
                $pitTaxAmount = ($grossSalary - $taxExemptOt) * 0.10;
            }
        } else {
            // Giảm trừ gia cảnh theo quy định
            $personalDeduction = 11000000.00; // Giảm trừ bản thân
            $dependentDeductionPerPerson = 4400000.00; // Giảm trừ người phụ thuộc
            $dependentsCount = (int) $employee->tax_dependents_count;
            $totalDependentDeduction = $dependentsCount * $dependentDeductionPerPerson;

            // Thu nhập tính thuế = Thu nhập chịu thuế - Các khoản bảo hiểm - Các khoản giảm trừ
            $taxableIncome = $grossSalary - $taxExemptOt;
            $assessableIncome = max(0.00, $taxableIncome - $totalInsurance - $personalDeduction - $totalDependentDeduction);

            // Tính thuế lũy tiến theo các bậc động
            $pitTaxAmount = $this->calculateProgressiveTax($assessableIncome, $taxBrackets);
        }

        // Lương thực nhận (Net Salary)
        $netSalary = max(0.00, $grossSalary - $totalInsurance - $pitTaxAmount);

        // Lưu hoặc cập nhật PayrollRecord
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
