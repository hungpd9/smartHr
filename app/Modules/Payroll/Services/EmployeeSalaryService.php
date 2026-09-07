<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryService
{
    public function __construct(
        protected TemporalLockService $temporalLockService
    ) {}

    /**
     * Danh sách mức lương của nhân viên theo tenant
     */
    public function listSalaries(int $tenantId, ?int $employeeId = null, ?string $search = null)
    {
        $query = EmployeeSalary::where('tenant_id', $tenantId)
            ->with(['employee.currentTimeline.department.currentVersion', 'employee.currentTimeline.position']);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('effective_date')->paginate(20);
    }

    /**
     * Thêm mới mức lương cá nhân theo chuỗi thời gian (Effective Dating)
     */
    public function createSalary(int $tenantId, array $data): EmployeeSalary
    {
        $employeeId = (int) $data['employee_id'];
        $effectiveDate = Carbon::parse($data['effective_date']);

        // 1. Kiểm tra kỳ lương có bị khóa không
        $this->temporalLockService->assertSalaryConfigNotLocked($tenantId, $employeeId, $effectiveDate);

        return DB::transaction(function () use ($tenantId, $employeeId, $effectiveDate, $data) {
            // 2. Tìm mức lương hiện tại đang mở (end_date is null) và cập nhật end_date nếu mức lương mới diễn ra sau
            $previousSalary = EmployeeSalary::where('tenant_id', $tenantId)
                ->where('employee_id', $employeeId)
                ->where('effective_date', '<', $effectiveDate->toDateString())
                ->where(function ($q) use ($effectiveDate) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $effectiveDate->toDateString());
                })
                ->latest('effective_date')
                ->first();

            if ($previousSalary) {
                $previousSalary->update([
                    'end_date' => $effectiveDate->copy()->subDay()->toDateString(),
                ]);
            }

            return EmployeeSalary::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'base_salary' => (float) $data['base_salary'],
                'insurance_salary' => isset($data['insurance_salary']) && $data['insurance_salary'] !== '' ? (float) $data['insurance_salary'] : null,
                'contract_type' => $data['contract_type'] ?? 'OFFICIAL',
                'effective_date' => $effectiveDate->toDateString(),
                'end_date' => !empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null,
                'notes' => $data['notes'] ?? null,
                'is_locked' => false,
            ]);
        });
    }

    /**
     * Cập nhật mức lương cá nhân
     */
    public function updateSalary(int $tenantId, int $salaryId, array $data): EmployeeSalary
    {
        $salary = EmployeeSalary::where('tenant_id', $tenantId)->findOrFail($salaryId);

        // Kiểm tra xem bản ghi đã bị khóa bất biến chưa
        if ($salary->is_locked) {
            throw ValidationException::withMessages([
                'salary' => ['Mức lương này đã được áp dụng trong kỳ lương đã khóa sổ, không thể chỉnh sửa.'],
            ]);
        }

        $effectiveDate = Carbon::parse($data['effective_date'] ?? $salary->effective_date);
        $this->temporalLockService->assertSalaryConfigNotLocked($tenantId, $salary->employee_id, $effectiveDate);

        $salary->update([
            'base_salary' => (float) ($data['base_salary'] ?? $salary->base_salary),
            'insurance_salary' => isset($data['insurance_salary']) && $data['insurance_salary'] !== '' ? (float) $data['insurance_salary'] : null,
            'contract_type' => $data['contract_type'] ?? $salary->contract_type,
            'effective_date' => $effectiveDate->toDateString(),
            'end_date' => !empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null,
            'notes' => $data['notes'] ?? $salary->notes,
        ]);

        return $salary;
    }

    /**
     * Xóa bản ghi mức lương
     */
    public function deleteSalary(int $tenantId, int $salaryId): void
    {
        $salary = EmployeeSalary::where('tenant_id', $tenantId)->findOrFail($salaryId);

        if ($salary->is_locked) {
            throw ValidationException::withMessages([
                'salary' => ['Mức lương này đã được áp dụng trong kỳ lương đã khóa sổ, không thể xóa.'],
            ]);
        }

        $effectiveDate = Carbon::parse($salary->effective_date);
        $this->temporalLockService->assertSalaryConfigNotLocked($tenantId, $salary->employee_id, $effectiveDate);

        $salary->delete();
    }

    /**
     * Khóa tất cả các bản ghi mức lương đã áp dụng trong kỳ lương vừa khóa sổ
     */
    public function lockSalariesForPeriod(int $tenantId, string $monthYear): int
    {
        $endOfMonth = Carbon::createFromFormat('Y-m', $monthYear)->endOfMonth()->toDateString();
        $startOfMonth = Carbon::createFromFormat('Y-m', $monthYear)->startOfMonth()->toDateString();

        return EmployeeSalary::where('tenant_id', $tenantId)
            ->where('effective_date', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startOfMonth);
            })
            ->update(['is_locked' => true]);
    }
}
