<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\LazyCollection;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\CoreHR\Models\EmployeeChild;

class ReportDataProviderService
{
    /**
     * Lấy dữ liệu phân trang và chỉ số tổng hợp để hiển thị trên bảng xem trước (Preview Table)
     *
     * @param int $tenantId
     * @param string $reportType
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $perPage
     * @return array{
     *     report_type: string,
     *     report_label: string,
     *     columns: array<int, array<string, mixed>>,
     *     rows: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     summary: array<string, mixed>
     * }
     */
    public function getReportData(
        int $tenantId,
        string $reportType,
        array $filters = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        $enum = ReportType::tryFrom($reportType) ?? ReportType::TIMESHEET_SUMMARY;
        $columns = $enum->columns();

        return match ($enum) {
            ReportType::TIMESHEET_SUMMARY => $this->getTimesheetSummaryData($tenantId, $filters, $page, $perPage, $columns),
            ReportType::PAYROLL_ANALYSIS => $this->getPayrollAnalysisData($tenantId, $filters, $page, $perPage, $columns),
            ReportType::HEADCOUNT_TURNOVER => $this->getHeadcountTurnoverData($tenantId, $filters, $page, $perPage, $columns),
            ReportType::COMPLIANCE_AUDIT => $this->getComplianceAuditData($tenantId, $filters, $page, $perPage, $columns),
            ReportType::CHILD_WELFARE => $this->getChildWelfareData($tenantId, $filters, $page, $perPage, $columns),
        };
    }

    /**
     * Trả về LazyCollection chứa toàn bộ các dòng dữ liệu để phục vụ Streaming Export ra file lớn
     */
    public function getReportCursor(int $tenantId, string $reportType, array $filters = []): LazyCollection
    {
        $enum = ReportType::tryFrom($reportType) ?? ReportType::TIMESHEET_SUMMARY;

        return match ($enum) {
            ReportType::TIMESHEET_SUMMARY => $this->cursorTimesheetSummary($tenantId, $filters),
            ReportType::PAYROLL_ANALYSIS => $this->cursorPayrollAnalysis($tenantId, $filters),
            ReportType::HEADCOUNT_TURNOVER => $this->cursorHeadcountTurnover($tenantId, $filters),
            ReportType::COMPLIANCE_AUDIT => $this->cursorComplianceAudit($tenantId, $filters),
            ReportType::CHILD_WELFARE => $this->cursorChildWelfare($tenantId, $filters),
        };
    }

    // ==========================================
    // 1. TIMESHEET SUMMARY
    // ==========================================
    protected function buildTimesheetQuery(int $tenantId, array $filters)
    {
        $month = $filters['month_year'] ?? Carbon::now()->format('Y-m');

        $query = MonthlyAttendance::with([
            'employee.currentTimeline.department.currentVersion',
        ])
            ->where('tenant_id', $tenantId)
            ->where('month_year', $month);

        if (!empty($filters['branch_id'])) {
            $query->whereHas('employee.currentTimeline.department', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        if (!empty($filters['department_id'])) {
            $query->whereHas('employee.currentTimeline', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                  ->orWhere('user_code', 'like', $search);
            });
        }

        return $query;
    }

    protected function getTimesheetSummaryData(int $tenantId, array $filters, int $page, int $perPage, array $columns): array
    {
        $query = $this->buildTimesheetQuery($tenantId, $filters);
        $total = $query->count();

        // Tóm tắt KPIs
        $allRecords = (clone $query)->get();
        $summary = [
            'total_employees' => $total,
            'total_actual_days' => round((float)$allRecords->sum('total_standard_days'), 1),
            'total_ot_hours' => round((float)$allRecords->sum('total_ot_hours'), 1),
            'total_late_minutes' => (int)$allRecords->sum('total_late_minutes'),
        ];

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $items->map(function (MonthlyAttendance $att) {
            $emp = $att->employee;
            $dept = $emp?->currentTimeline?->department?->currentVersion?->name ?? '---';
            $actualDays = (float)$att->total_standard_days;

            return [
                'user_code' => $emp?->user_code ?? '---',
                'employee_name' => $emp?->full_name ?? '---',
                'department' => $dept,
                'standard_days' => 22.0,
                'actual_work_days' => $actualDays,
                'paid_leave_days' => 0.0,
                'unpaid_leave_days' => 0.0,
                'late_minutes' => (int)$att->total_late_minutes,
                'early_minutes' => 0,
                'ot_normal_hours' => (float)$att->total_ot_hours,
                'ot_night_hours' => 0.0,
                'ot_weekend_hours' => 0.0,
                'total_ot_hours' => (float)$att->total_ot_hours,
                'submission_status' => $att->submission_status ?? 'DRAFT',
            ];
        })->toArray();

        return [
            'report_type' => ReportType::TIMESHEET_SUMMARY->value,
            'report_label' => ReportType::TIMESHEET_SUMMARY->label(),
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'summary' => $summary,
        ];
    }

    protected function cursorTimesheetSummary(int $tenantId, array $filters): LazyCollection
    {
        return $this->buildTimesheetQuery($tenantId, $filters)
            ->cursor()
            ->map(function (MonthlyAttendance $att) {
                $emp = $att->employee;
                return [
                    $emp?->user_code ?? '',
                    $emp?->full_name ?? '',
                    $emp?->currentTimeline?->department?->currentVersion?->name ?? '',
                    22.0,
                    (float)$att->total_standard_days,
                    0.0,
                    0.0,
                    (int)$att->total_late_minutes,
                    0,
                    (float)$att->total_ot_hours,
                    0.0,
                    0.0,
                    (float)$att->total_ot_hours,
                    $att->submission_status ?? 'DRAFT',
                ];
            });
    }

    // ==========================================
    // 2. PAYROLL ANALYSIS
    // ==========================================
    protected function buildPayrollQuery(int $tenantId, array $filters)
    {
        $month = $filters['month_year'] ?? Carbon::now()->format('Y-m');

        $query = PayrollRecord::with([
            'employee.currentTimeline.department.currentVersion',
        ])
            ->where('tenant_id', $tenantId)
            ->where('month_year', $month);

        if (!empty($filters['branch_id'])) {
            $query->whereHas('employee.currentTimeline.department', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        if (!empty($filters['department_id'])) {
            $query->whereHas('employee.currentTimeline', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                  ->orWhere('user_code', 'like', $search);
            });
        }

        return $query;
    }

    protected function getPayrollAnalysisData(int $tenantId, array $filters, int $page, int $perPage, array $columns): array
    {
        $query = $this->buildPayrollQuery($tenantId, $filters);
        $total = $query->count();

        $allRecords = (clone $query)->get();
        $totalGross = (float)$allRecords->sum('gross_salary');
        $totalNet = (float)$allRecords->sum('net_salary');
        $totalTax = (float)$allRecords->sum('pit_tax_amount');
        $totalEmployerCost = (float)$allRecords->sum(function (PayrollRecord $r) {
            $breakdown = $r->salary_breakdown ?? [];
            return (float)($breakdown['employer_cost'] ?? ($r->gross_salary * 0.235));
        });

        $summary = [
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'total_tax' => $totalTax,
            'total_employer_cost' => $totalEmployerCost,
        ];

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $items->map(function (PayrollRecord $p) {
            $emp = $p->employee;
            $dept = $emp?->currentTimeline?->department?->currentVersion?->name ?? '---';
            $breakdown = $p->salary_breakdown ?? [];
            $baseSalary = (float)($breakdown['base_salary'] ?? ($p->gross_salary * 0.7));
            $otTaxable = (float)($p->taxable_ot_amount ?? 0);
            $otTaxExempt = (float)($p->tax_exempt_ot_amount ?? 0);
            $allowancesTotal = (float)(
                ($p->position_allowance_amount ?? 0) +
                ($p->child_allowance_amount ?? 0) +
                ($p->certificate_allowance_amount ?? 0)
            );
            $empInsurance = (float)(
                ($p->social_insurance_employee ?? 0) +
                ($p->health_insurance_employee ?? 0) +
                ($p->unemployment_insurance_employee ?? 0)
            );
            $employerCost = (float)($breakdown['employer_cost'] ?? ($p->gross_salary * 0.235));

            return [
                'user_code' => $emp?->user_code ?? '---',
                'employee_name' => $emp?->full_name ?? '---',
                'department' => $dept,
                'base_salary' => $baseSalary,
                'gross_salary' => (float)$p->gross_salary,
                'ot_taxable' => $otTaxable,
                'ot_tax_exempt' => $otTaxExempt,
                'allowances_total' => $allowancesTotal,
                'employee_insurance' => $empInsurance,
                'employer_cost' => $employerCost,
                'pit_tax' => (float)$p->pit_tax_amount,
                'net_salary' => (float)$p->net_salary,
            ];
        })->toArray();

        return [
            'report_type' => ReportType::PAYROLL_ANALYSIS->value,
            'report_label' => ReportType::PAYROLL_ANALYSIS->label(),
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'summary' => $summary,
        ];
    }

    protected function cursorPayrollAnalysis(int $tenantId, array $filters): LazyCollection
    {
        return $this->buildPayrollQuery($tenantId, $filters)
            ->cursor()
            ->map(function (PayrollRecord $p) {
                $emp = $p->employee;
                $breakdown = $p->salary_breakdown ?? [];
                $baseSalary = (float)($breakdown['base_salary'] ?? ($p->gross_salary * 0.7));
                $otTaxable = (float)($p->taxable_ot_amount ?? 0);
                $otTaxExempt = (float)($p->tax_exempt_ot_amount ?? 0);
                $allowancesTotal = (float)(
                    ($p->position_allowance_amount ?? 0) +
                    ($p->child_allowance_amount ?? 0) +
                    ($p->certificate_allowance_amount ?? 0)
                );
                $empInsurance = (float)(
                    ($p->social_insurance_employee ?? 0) +
                    ($p->health_insurance_employee ?? 0) +
                    ($p->unemployment_insurance_employee ?? 0)
                );
                $employerCost = (float)($breakdown['employer_cost'] ?? ($p->gross_salary * 0.235));

                return [
                    $emp?->user_code ?? '',
                    $emp?->full_name ?? '',
                    $emp?->currentTimeline?->department?->currentVersion?->name ?? '',
                    $baseSalary,
                    (float)$p->gross_salary,
                    $otTaxable,
                    $otTaxExempt,
                    $allowancesTotal,
                    $empInsurance,
                    $employerCost,
                    (float)$p->pit_tax_amount,
                    (float)$p->net_salary,
                ];
            });
    }

    // ==========================================
    // 3. HEADCOUNT & TURNOVER
    // ==========================================
    protected function getHeadcountTurnoverData(int $tenantId, array $filters, int $page, int $perPage, array $columns): array
    {
        $month = $filters['month_year'] ?? Carbon::now()->format('Y-m');
        $departments = Department::with('currentVersion')
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->get();

        $rows = [];
        $totalHeadcountSum = 0;
        $totalOnboardSum = 0;
        $totalOffboardSum = 0;

        foreach ($departments as $dept) {
            $deptId = $dept->id;
            $deptName = $dept->currentVersion?->name ?? $dept->code;

            $empQuery = Employee::where('tenant_id', $tenantId)
                ->whereHas('currentTimeline', fn ($q) => $q->where('department_id', $deptId))
                ->where('status', '!=', 'TERMINATED');

            $headcount = $empQuery->count();
            $officialCount = (clone $empQuery)->where('contract_type', '!=', 'PROBATION')->count();
            $probationCount = (clone $empQuery)->where('contract_type', 'PROBATION')->count();

            // Tuyển mới và Thôi việc trong tháng
            $onboardCount = EmployeeCareerTimeline::whereHas('employee', fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('department_id', $deptId)
                ->where('event_type', 'ONBOARDING')
                ->where('effective_date', 'like', "{$month}%")
                ->count();

            $offboardCount = EmployeeCareerTimeline::whereHas('employee', fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('department_id', $deptId)
                ->where('event_type', 'OFFBOARDING')
                ->where('effective_date', 'like', "{$month}%")
                ->count();

            $netGrowth = $onboardCount - $offboardCount;
            $turnoverRate = ($headcount + $offboardCount) > 0
                ? round(($offboardCount / ($headcount + $offboardCount)) * 100, 1)
                : 0.0;

            $rows[] = [
                'department' => $deptName,
                'total_headcount' => $headcount,
                'official_count' => $officialCount,
                'probation_count' => $probationCount,
                'onboard_count' => $onboardCount,
                'offboard_count' => $offboardCount,
                'net_growth' => $netGrowth,
                'turnover_rate' => $turnoverRate,
            ];

            $totalHeadcountSum += $headcount;
            $totalOnboardSum += $onboardCount;
            $totalOffboardSum += $offboardCount;
        }

        $total = count($rows);
        $pagedRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $summary = [
            'total_headcount' => $totalHeadcountSum,
            'total_onboard' => $totalOnboardSum,
            'total_offboard' => $totalOffboardSum,
            'net_growth' => $totalOnboardSum - $totalOffboardSum,
        ];

        return [
            'report_type' => ReportType::HEADCOUNT_TURNOVER->value,
            'report_label' => ReportType::HEADCOUNT_TURNOVER->label(),
            'columns' => $columns,
            'rows' => $pagedRows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'summary' => $summary,
        ];
    }

    protected function cursorHeadcountTurnover(int $tenantId, array $filters): LazyCollection
    {
        $res = $this->getHeadcountTurnoverData($tenantId, $filters, 1, 1000, []);
        return LazyCollection::make(function () use ($res) {
            foreach ($res['rows'] as $r) {
                yield [
                    $r['department'],
                    $r['total_headcount'],
                    $r['official_count'],
                    $r['probation_count'],
                    $r['onboard_count'],
                    $r['offboard_count'],
                    $r['net_growth'],
                    $r['turnover_rate'],
                ];
            }
        });
    }

    // ==========================================
    // 4. COMPLIANCE AUDIT
    // ==========================================
    protected function buildComplianceQuery(int $tenantId, array $filters)
    {
        $query = ComplianceAlert::with([
            'employee.currentTimeline.department.currentVersion',
        ])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['is_resolved']) && $filters['is_resolved'] !== 'ALL') {
            $query->where('is_resolved', $filters['is_resolved'] === 'RESOLVED');
        }

        if (!empty($filters['month_year'])) {
            $query->where('created_at', 'like', "{$filters['month_year']}%");
        }

        return $query;
    }

    protected function getComplianceAuditData(int $tenantId, array $filters, int $page, int $perPage, array $columns): array
    {
        $query = $this->buildComplianceQuery($tenantId, $filters);
        $total = $query->count();

        $allRecords = (clone $query)->get();
        $summary = [
            'total_alerts' => $total,
            'critical_count' => $allRecords->where('severity', 'CRITICAL')->count(),
            'warning_count' => $allRecords->where('severity', 'WARNING')->count(),
            'resolved_count' => $allRecords->where('is_resolved', true)->count(),
        ];

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $items->map(function (ComplianceAlert $alert) {
            $emp = $alert->employee;
            $dept = $emp?->currentTimeline?->department?->currentVersion?->name ?? '---';

            return [
                'created_at' => Carbon::parse($alert->created_at)->format('d/m/Y H:i'),
                'severity' => $alert->severity,
                'alert_type_label' => $alert->title,
                'user_code' => $emp?->user_code ?? '---',
                'employee_name' => $emp?->full_name ?? '---',
                'department' => $dept,
                'labor_code_ref' => $alert->labor_code_reference ?? 'BLLĐ 2019',
                'status' => $alert->is_resolved ? 'ĐÃ KHẮC PHỤC' : 'CHỜ XỬ LÝ',
                'resolved_at' => $alert->resolved_at ? Carbon::parse($alert->resolved_at)->format('d/m/Y H:i') : '---',
            ];
        })->toArray();

        return [
            'report_type' => ReportType::COMPLIANCE_AUDIT->value,
            'report_label' => ReportType::COMPLIANCE_AUDIT->label(),
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'summary' => $summary,
        ];
    }

    protected function cursorComplianceAudit(int $tenantId, array $filters): LazyCollection
    {
        return $this->buildComplianceQuery($tenantId, $filters)
            ->cursor()
            ->map(function (ComplianceAlert $alert) {
                $emp = $alert->employee;
                return [
                    Carbon::parse($alert->created_at)->format('d/m/Y H:i'),
                    $alert->severity,
                    $alert->title,
                    $emp?->user_code ?? '',
                    $emp?->full_name ?? '',
                    $emp?->currentTimeline?->department?->currentVersion?->name ?? '',
                    $alert->labor_code_reference ?? 'BLLĐ 2019',
                    $alert->is_resolved ? 'ĐÃ KHẮC PHỤC' : 'CHỜ XỬ LÝ',
                    $alert->resolved_at ? Carbon::parse($alert->resolved_at)->format('d/m/Y H:i') : '',
                ];
            });
    }

    // ==========================================
    // 5. CHILD WELFARE & TAX DEPENDENTS
    // ==========================================
    protected function buildChildWelfareQuery(int $tenantId, array $filters)
    {
        $query = EmployeeChild::with([
            'employee.currentTimeline.department.currentVersion',
        ])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        if (!empty($filters['is_tax_dependent']) && $filters['is_tax_dependent'] !== 'ALL') {
            $query->where('is_tax_dependent', $filters['is_tax_dependent'] === 'YES');
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('full_name', 'like', $search)
                    ->orWhereHas('employee', function ($empQ) use ($search) {
                        $empQ->where('full_name', 'like', $search)
                             ->orWhere('user_code', 'like', $search);
                    });
            });
        }

        return $query;
    }

    protected function getChildWelfareData(int $tenantId, array $filters, int $page, int $perPage, array $columns): array
    {
        $query = $this->buildChildWelfareQuery($tenantId, $filters);
        $total = $query->count();

        $allRecords = (clone $query)->get();
        $summary = [
            'total_children' => $total,
            'under_12m_count' => $allRecords->filter(fn ($c) => $c->birth_date && Carbon::parse($c->birth_date)->diffInMonths(now()) < 12)->count(),
            'tax_dependent_count' => $allRecords->where('is_tax_dependent', true)->count(),
            'total_monthly_allowance' => (float)$allRecords->sum('monthly_allowance_amount'),
        ];

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $items->map(function (EmployeeChild $child) {
            $emp = $child->employee;
            $dept = $emp?->currentTimeline?->department?->currentVersion?->name ?? '---';
            $ageMonths = $child->birth_date ? Carbon::parse($child->birth_date)->diffInMonths(now()) : 0;
            $isUnder12m = $ageMonths < 12;

            return [
                'user_code' => $emp?->user_code ?? '---',
                'employee_name' => $emp?->full_name ?? '---',
                'department' => $dept,
                'child_name' => $child->full_name,
                'birth_date' => $child->birth_date ? Carbon::parse($child->birth_date)->format('d/m/Y') : '---',
                'age_months' => $ageMonths,
                'is_under_12m' => $isUnder12m ? 'ĐỦ ĐIỀU KIỆN' : 'KHÔNG',
                'is_tax_dependent' => $child->is_tax_dependent ? 'CÓ (NPT)' : 'KHÔNG',
                'tax_code' => $child->tax_dependent_code ?? '---',
                'monthly_allowance' => (float)$child->monthly_allowance_amount,
            ];
        })->toArray();

        return [
            'report_type' => ReportType::CHILD_WELFARE->value,
            'report_label' => ReportType::CHILD_WELFARE->label(),
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'summary' => $summary,
        ];
    }

    protected function cursorChildWelfare(int $tenantId, array $filters): LazyCollection
    {
        return $this->buildChildWelfareQuery($tenantId, $filters)
            ->cursor()
            ->map(function (EmployeeChild $child) {
                $emp = $child->employee;
                $ageMonths = $child->birth_date ? Carbon::parse($child->birth_date)->diffInMonths(now()) : 0;
                $isUnder12m = $ageMonths < 12;

                return [
                    $emp?->user_code ?? '',
                    $emp?->full_name ?? '',
                    $emp?->currentTimeline?->department?->currentVersion?->name ?? '',
                    $child->full_name,
                    $child->birth_date ? Carbon::parse($child->birth_date)->format('d/m/Y') : '',
                    $ageMonths,
                    $isUnder12m ? 'ĐỦ ĐIỀU KIỆN' : 'KHÔNG',
                    $child->is_tax_dependent ? 'CÓ (NPT)' : 'KHÔNG',
                    $child->tax_dependent_code ?? '',
                    (float)$child->monthly_allowance_amount,
                ];
            });
    }
}
