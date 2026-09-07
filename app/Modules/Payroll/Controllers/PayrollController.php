<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Services\AuditTrailService;
use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\Payroll\Services\EmployeeSalaryService;
use App\Modules\Payroll\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollCalculationService,
        protected EmployeeSalaryService $employeeSalaryService,
        protected TemporalLockService $temporalLockService,
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Màn hình Bảng lương doanh nghiệp & Điều hành Chu trình tính lương
     */
    public function index(Request $request): Response
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->query('month_year', Carbon::now()->format('Y-m'));

        // 1. Lấy thông tin PayrollPeriod
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->with(['lockedBy', 'policyVersion'])
            ->first();

        // 2. Lấy danh sách PayrollRecord đã tính
        $records = PayrollRecord::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->with(['employee.currentTimeline.department.currentVersion', 'employee.currentTimeline.position'])
            ->get();

        // 3. Lấy danh sách nhân viên để đối chiếu trạng thái duyệt bảng chấm công
        $allEmployees = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['OFFICIAL', 'PROBATION', 'ON_LEAVE'])
            ->select('id', 'user_code', 'full_name', 'status', 'gender')
            ->orderBy('full_name')
            ->get();

        $monthlyAttendances = MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->get()
            ->keyBy('employee_id');

        $unapprovedEmployees = [];
        foreach ($allEmployees as $emp) {
            $att = $monthlyAttendances->get($emp->id);
            if (!$att || !$att->is_approved) {
                $unapprovedEmployees[] = [
                    'employee_id' => $emp->id,
                    'user_code' => $emp->user_code,
                    'full_name' => $emp->full_name,
                    'status' => $emp->status,
                    'has_timesheet' => (bool) $att,
                    'is_approved' => (bool) ($att?->is_approved ?? false),
                    'total_days' => (float) ($att?->total_standard_days ?? 0),
                    'reason' => !$att
                        ? 'Chưa tổng hợp bảng công tháng'
                        : 'Bảng chấm công chưa được Quản lý duyệt (is_approved = false)',
                ];
            }
        }

        // 4. Lấy danh sách mức lương cá nhân của toàn bộ nhân sự (Employee Salaries)
        $employeeSalaries = EmployeeSalary::where('tenant_id', $tenantId)
            ->with(['employee.currentTimeline.department.currentVersion', 'employee.currentTimeline.position'])
            ->orderByDesc('effective_date')
            ->get();

        return Inertia::render('Payroll/Index', [
            'monthYear' => $monthYear,
            'payrollPeriod' => $payrollPeriod,
            'records' => $records,
            'unapprovedEmployees' => $unapprovedEmployees,
            'employeeSalaries' => $employeeSalaries,
            'allEmployees' => $allEmployees,
            'totalEmployeesCount' => $allEmployees->count(),
            'calculatedCount' => $records->count(),
            'unapprovedCount' => count($unapprovedEmployees),
        ]);
    }

    /**
     * Kích hoạt động cơ tính lương cho toàn bộ Tenant theo kỳ tháng
     */
    public function calculate(Request $request): RedirectResponse
    {
        $request->validate([
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->input('month_year');

        try {
            $result = $this->payrollCalculationService->calculateForMonth($tenantId, $monthYear);

            $msg = "Tính lương kỳ {$monthYear} hoàn tất: Đã tính {$result['calculated_count']} nhân sự. Tự động bỏ qua {$result['skipped_count']} nhân sự chưa duyệt chấm công.";
            return redirect()->route('payroll.index', ['month_year' => $monthYear])
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', 'Lỗi tính toán lương: ' . $e->getMessage());
        }
    }

    /**
     * Khóa sổ kỳ lương bất biến (Chặn mọi sửa đổi hồi tố)
     */
    public function lockPeriod(Request $request): RedirectResponse
    {
        $request->validate([
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->input('month_year');
        $userId = (int) ($request->user()->id ?? 1);

        $period = PayrollPeriod::firstOrCreate(
            ['tenant_id' => $tenantId, 'month_year' => $monthYear],
            ['status' => 'DRAFT', 'is_locked' => false]
        );

        if ($period->is_locked) {
            return redirect()->back()->with('warning', "Kỳ lương {$monthYear} đã được khóa từ trước.");
        }

        $period->update([
            'is_locked' => true,
            'status' => 'APPROVED',
            'locked_by' => $userId,
            'locked_at' => Carbon::now(),
        ]);

        // Tự động khóa các mức lương cá nhân có hiệu lực trong kỳ lương này
        $this->employeeSalaryService->lockSalariesForPeriod($tenantId, $monthYear);

        $this->auditTrailService->log(
            tenantId: $tenantId,
            action: 'LOCK_PAYROLL_PERIOD',
            entityType: 'PayrollPeriod',
            entityId: $period->id,
            oldValues: ['is_locked' => false, 'status' => 'DRAFT'],
            newValues: ['is_locked' => true, 'status' => 'APPROVED', 'locked_at' => Carbon::now()->toIso8601String()],
            performedBy: $userId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('payroll.index', ['month_year' => $monthYear])
            ->with('success', "Đã khóa sổ kỳ lương {$monthYear} thành công. Toàn bộ bảng lương và cấu hình lương đã được bảo vệ bất biến.");
    }

    /**
     * API Xem chi tiết phiếu lương điện tử của 1 nhân viên
     */
    public function payslip(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);

        $record = PayrollRecord::where('tenant_id', $tenantId)
            ->with(['employee.currentTimeline.department.currentVersion', 'employee.currentTimeline.position'])
            ->findOrFail($id);

        return response()->json([
            'record' => $record,
            'salary_breakdown' => $record->salary_breakdown,
        ]);
    }

    /**
     * Phê duyệt nhanh bảng chấm công của nhân viên (Dành cho Quản lý / HR)
     */
    public function approveTimesheet(Request $request, int $employeeId): RedirectResponse
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->input('month_year', Carbon::now()->format('Y-m'));
        $userId = (int) ($request->user()->id ?? 1);

        $attendance = MonthlyAttendance::firstOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employeeId, 'month_year' => $monthYear],
            ['total_standard_days' => 22.0, 'total_ot_hours' => 0.0]
        );

        $attendance->update([
            'is_approved' => true,
            'approved_by' => $userId,
            'approved_at' => Carbon::now(),
        ]);

        return redirect()->back()
            ->with('success', 'Đã phê duyệt bảng chấm công nhân viên thành công.');
    }

    /**
     * Thêm mới mức lương thỏa thuận cá nhân cho nhân sự
     */
    public function storeSalary(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'insurance_salary' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['required', 'string', 'in:OFFICIAL,PROBATION,SEASONAL,INTERN'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:effective_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $tenantId = (int) ($request->user()->tenant_id ?? 1);

        $this->employeeSalaryService->createSalary($tenantId, $request->all());

        return redirect()->back()->with('success', 'Đã thêm mức lương mới cho nhân sự thành công.');
    }

    /**
     * Cập nhật mức lương thỏa thuận cá nhân
     */
    public function updateSalary(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'base_salary' => ['required', 'numeric', 'min:0'],
            'insurance_salary' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['required', 'string', 'in:OFFICIAL,PROBATION,SEASONAL,INTERN'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:effective_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $tenantId = (int) ($request->user()->tenant_id ?? 1);

        $this->employeeSalaryService->updateSalary($tenantId, $id, $request->all());

        return redirect()->back()->with('success', 'Đã cập nhật mức lương nhân sự thành công.');
    }

    /**
     * Xóa bản ghi mức lương cá nhân
     */
    public function destroySalary(Request $request, int $id): RedirectResponse
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);

        $this->employeeSalaryService->deleteSalary($tenantId, $id);

        return redirect()->back()->with('success', 'Đã xóa bản ghi mức lương thành công.');
    }
}
