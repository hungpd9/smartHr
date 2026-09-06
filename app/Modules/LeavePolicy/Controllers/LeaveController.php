<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LeavePolicy\Services\LeaveBalanceService;
use App\Modules\LeavePolicy\Services\OvertimeComplianceService;
use App\Modules\LeavePolicy\Requests\AdjustLeaveRequest;
use App\Modules\LeavePolicy\Requests\ConvertOtRequest;
use App\Modules\LeavePolicy\Models\LeaveBalance;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Department;
use App\Enums\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $leaveBalanceService,
        protected OvertimeComplianceService $otComplianceService
    ) {}

    /**
     * Màn hình Quản trị Quỹ Phép Năm & Quỹ Nghỉ Bù (Leave Balances Matrix)
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $currentYear = (int) Carbon::today()->year;
        $selectedYear = (int) $request->query('year', (string) $currentYear);
        $departmentId = $request->query('department_id') ? (int) $request->query('department_id') : null;
        $search = $request->query('search');

        // Tự động khởi tạo quỹ phép năm nếu chưa có bản ghi nào trong năm
        $balancesCount = LeaveBalance::where('tenant_id', $tenantId)->where('year', $selectedYear)->count();
        if ($balancesCount === 0) {
            $activeEmployees = Employee::where('tenant_id', $tenantId)
                ->where('status', '!=', 'TERMINATED')
                ->get();
            foreach ($activeEmployees as $emp) {
                $this->leaveBalanceService->initializeOrUpdateAnnualBalance($emp, $selectedYear);
            }
        }

        // Truy vấn dữ liệu số dư quỹ phép
        $balancesQuery = LeaveBalance::where('tenant_id', $tenantId)
            ->where('year', $selectedYear)
            ->with([
                'employee' => function ($query) {
                    $query->with([
                        'currentTimeline.department.currentVersion',
                        'currentTimeline.position',
                        'children'
                    ]);
                },
                'transactions' => function ($query) {
                    $query->latest('created_at')->limit(10);
                }
            ]);

        // Giới hạn quyền xem nếu là EMPLOYEE thông thường
        if ($user->role === UserRole::EMPLOYEE) {
            $employeeCode = $user->employee_code ?? $user->email;
            $balancesQuery->whereHas('employee', function ($q) use ($employeeCode) {
                $q->where('user_code', $employeeCode);
            });
        } elseif ($departmentId) {
            $balancesQuery->whereHas('employee.currentTimeline', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($search) {
            $balancesQuery->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%");
            });
        }

        $leaveBalances = $balancesQuery->orderBy('employee_id')->orderBy('leave_type')->get();

        // Danh sách nhân viên để phục vụ Modal quy đổi OT
        $employeesList = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->select(['id', 'user_code', 'full_name'])
            ->get();

        // Danh sách phòng ban phục vụ bộ lọc
        $departments = Department::where('tenant_id', $tenantId)
            ->with('currentVersion')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->currentVersion?->name ?? $d->code,
            ]);

        // Tính toán các chỉ số KPI tóm tắt
        $annualBalances = $leaveBalances->where('leave_type', 'ANNUAL');
        $compBalances = $leaveBalances->where('leave_type', 'COMPENSATION');

        $kpis = [
            'total_statutory_allocated' => round((float) $annualBalances->sum('statutory_allocated_days'), 1),
            'total_seniority_bonus' => round((float) $annualBalances->sum('seniority_bonus_days'), 1),
            'total_welfare_bonus' => round((float) $annualBalances->sum('welfare_bonus_days'), 1),
            'total_carried_over' => round((float) $annualBalances->sum('carried_over_days'), 1),
            'total_used' => round((float) $annualBalances->sum('used_days'), 1),
            'total_remaining' => round((float) $annualBalances->sum('remaining_days'), 1),
            'total_comp_off_remaining' => round((float) $compBalances->sum('remaining_days'), 1),
        ];

        return Inertia::render('Leave/Balances', [
            'balances' => $leaveBalances,
            'kpis' => $kpis,
            'departments' => $departments,
            'employeesList' => $employeesList,
            'filters' => [
                'year' => $selectedYear,
                'department_id' => $departmentId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Điều chỉnh quỹ phép thủ công (Manual Adjustment)
     */
    public function adjust(AdjustLeaveRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $employee = Employee::where('user_code', $user->employee_code ?? $user->email)->first();
        $actorId = $employee?->id;

        $this->leaveBalanceService->adjustBalance(
            (int) $request->validated('leave_balance_id'),
            (float) $request->validated('days'),
            $request->validated('transaction_type'),
            $request->validated('description'),
            $actorId
        );

        return redirect()->back()->with('success', 'Điều chỉnh quỹ phép thành công.');
    }

    /**
     * Tính toán & Cập nhật lại toàn bộ quỹ phép năm cho nhân sự
     */
    public function recalculate(Request $request): RedirectResponse
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 1);
        $year = (int) $request->input('year', (string) Carbon::today()->year);

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->get();

        foreach ($employees as $employee) {
            $this->leaveBalanceService->initializeOrUpdateAnnualBalance($employee, $year);
        }

        // Tự động kiểm tra hết hạn phép tồn nếu quá mốc 31/03
        $this->leaveBalanceService->expireCarriedOverBalances($year);

        return redirect()->back()->with('success', "Đã tính toán và cập nhật quỹ phép năm {$year} cho toàn bộ nhân sự.");
    }

    /**
     * Quy đổi số giờ làm thêm (OT) sang ngày nghỉ bù (Comp-off)
     */
    public function convertOt(ConvertOtRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $actorEmployee = Employee::where('user_code', $user->employee_code ?? $user->email)->first();

        $targetEmployee = Employee::findOrFail($request->validated('employee_id'));
        $year = (int) $request->validated('year');
        $otHours = (float) $request->validated('ot_hours');
        $rate = (float) ($request->validated('conversion_rate') ?? 8.0);
        $description = $request->validated('description');

        $this->leaveBalanceService->convertOtToCompOff(
            $targetEmployee,
            $year,
            $otHours,
            $rate,
            $description,
            $actorEmployee?->id
        );

        return redirect()->back()->with('success', "Quy đổi {$otHours}h làm thêm giờ sang ngày nghỉ bù thành công.");
    }

    /**
     * Màn hình Giám sát Trần Giờ Làm Thêm (OT Compliance Monitoring Dashboard)
     */
    public function otAccumulations(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $currentYear = (int) Carbon::today()->year;
        $selectedYear = (int) $request->query('year', (string) $currentYear);
        $departmentId = $request->query('department_id') ? (int) $request->query('department_id') : null;
        $warningFilter = $request->query('warning_filter'); // 'all' | 'warning' | 'breached'

        // Tự động đồng bộ số giờ OT từ bảng công nếu chưa có dữ liệu
        $count = OvertimeAccumulation::where('tenant_id', $tenantId)->where('year', $selectedYear)->count();
        if ($count === 0) {
            $activeEmployees = Employee::where('tenant_id', $tenantId)
                ->where('status', '!=', 'TERMINATED')
                ->get();
            foreach ($activeEmployees as $emp) {
                $this->otComplianceService->syncEmployeeOvertime($emp, $selectedYear);
            }
        }

        $query = OvertimeAccumulation::where('tenant_id', $tenantId)
            ->where('year', $selectedYear)
            ->with([
                'employee' => function ($q) {
                    $q->with(['currentTimeline.department.currentVersion', 'currentTimeline.position']);
                }
            ]);

        if ($user->role === UserRole::EMPLOYEE) {
            $employeeCode = $user->employee_code ?? $user->email;
            $query->whereHas('employee', function ($q) use ($employeeCode) {
                $q->where('user_code', $employeeCode);
            });
        } elseif ($departmentId) {
            $query->whereHas('employee.currentTimeline', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $records = $query->get();

        if ($warningFilter === 'breached') {
            $records = $records->filter(function ($r) {
                return $r->is_exceeded_200h || collect($r->monthly_ot_json ?? [])->contains(fn($h) => (float) $h > 40.0);
            })->values();
        } elseif ($warningFilter === 'warning') {
            $records = $records->filter(function ($r) {
                return collect($r->monthly_ot_json ?? [])->contains(fn($h) => (float) $h >= 35.0 && (float) $h <= 40.0);
            })->values();
        }

        $summary = $this->otComplianceService->getTenantOtComplianceSummary($tenantId, $selectedYear);
        $departments = Department::where('tenant_id', $tenantId)
            ->with('currentVersion')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->currentVersion?->name ?? $d->code,
            ]);

        return Inertia::render('Leave/OvertimeAccumulations', [
            'accumulations' => $records,
            'summary' => $summary,
            'departments' => $departments,
            'filters' => [
                'year' => $selectedYear,
                'department_id' => $departmentId,
                'warning_filter' => $warningFilter,
            ],
        ]);
    }

    /**
     * Đồng bộ lại số giờ làm thêm từ bảng chấm công
     */
    public function syncOt(Request $request): RedirectResponse
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 1);
        $year = (int) $request->input('year', (string) Carbon::today()->year);

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->get();

        foreach ($employees as $employee) {
            $this->otComplianceService->syncEmployeeOvertime($employee, $year);
        }

        return redirect()->back()->with('success', "Đồng bộ giờ làm thêm năm {$year} thành công.");
    }
}
