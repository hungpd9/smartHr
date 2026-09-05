<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\CoreHR\Models\Position;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeService
{
    /**
     * Tạo mới nhân sự và tự động kích hoạt mốc Timeline ONBOARDING
     */
    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $employee = Employee::create([
                'tenant_id' => $data['tenant_id'] ?? 1,
                'user_code' => $data['user_code'],
                'attendance_code' => $data['attendance_code'] ?? null,
                'full_name' => $data['full_name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'citizen_id' => $data['citizen_id'] ?? null,
                'encrypted_citizen_id' => !empty($data['citizen_id']) ? 'ENC_' . $data['citizen_id'] : null,
                'hometown' => $data['hometown'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'temporary_address' => $data['temporary_address'] ?? null,
                'join_date' => $data['join_date'],
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'status' => $data['status'] ?? 'PROBATION',
                'is_pregnant' => (bool) ($data['is_pregnant'] ?? false),
                'pregnancy_start_date' => $data['pregnancy_start_date'] ?? null,
                'pregnancy_weeks_at_start' => $data['pregnancy_weeks_at_start'] ?? null,
                'expected_due_date' => $data['expected_due_date'] ?? null,
            ]);

            $position = Position::find($data['position_id']);

            // Tự động ghi nhận mốc sự kiện Gia nhập công ty (Onboarding)
            EmployeeCareerTimeline::create([
                'employee_id' => $employee->id,
                'event_type' => 'ONBOARDING',
                'department_id' => $data['department_id'],
                'position_id' => $data['position_id'],
                'job_title' => $position?->name ?? 'Nhân viên mới',
                'manager_id' => null,
                'effective_date' => $data['join_date'],
                'end_date' => null,
                'decision_number' => 'QĐ-ONBOARD-' . $employee->user_code,
                'decision_date' => $data['join_date'],
                'notes' => 'Tiếp nhận nhân sự mới vào tổ chức',
            ]);

            return $employee;
        });
    }

    /**
     * Cập nhật thông tin hồ sơ nhân sự
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $updateData = [
            'full_name' => $data['full_name'],
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'attendance_code' => $data['attendance_code'] ?? null,
            'citizen_id' => $data['citizen_id'] ?? null,
            'hometown' => $data['hometown'] ?? null,
            'permanent_address' => $data['permanent_address'] ?? null,
            'temporary_address' => $data['temporary_address'] ?? null,
            'join_date' => $data['join_date'],
            'probation_end_date' => $data['probation_end_date'] ?? null,
            'status' => $data['status'] ?? $employee->status,
            'is_pregnant' => (bool) ($data['is_pregnant'] ?? false),
            'pregnancy_start_date' => $data['pregnancy_start_date'] ?? null,
            'pregnancy_weeks_at_start' => $data['pregnancy_weeks_at_start'] ?? null,
            'expected_due_date' => $data['expected_due_date'] ?? null,
        ];

        if (!empty($data['citizen_id'])) {
            $updateData['encrypted_citizen_id'] = 'ENC_' . $data['citizen_id'];
        }

        $employee->update($updateData);

        return $employee;
    }

    /**
     * Quy trình Thôi việc (Offboarding) kèm cập nhật timeline và thâm niên
     */
    public function offboardEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $resignedDate = Carbon::parse($data['resigned_date']);

            // Đóng mốc sự nghiệp hiện tại
            $currentTimeline = EmployeeCareerTimeline::where('employee_id', $employee->id)
                ->whereNull('end_date')
                ->latest('effective_date')
                ->first();

            if ($currentTimeline) {
                $currentTimeline->update([
                    'end_date' => $resignedDate->copy()->subDay()->format('Y-m-d'),
                ]);
            }

            // Cập nhật trạng thái nhân viên
            $employee->update([
                'status' => 'TERMINATED',
                'resigned_date' => $resignedDate->format('Y-m-d'),
                'resignation_reason' => $data['resignation_reason'],
            ]);

            $seniority = $employee->fresh()->seniority_text;

            // Tạo mốc sự kiện OFFBOARDING trong Career Timeline
            EmployeeCareerTimeline::create([
                'employee_id' => $employee->id,
                'event_type' => 'OFFBOARDING',
                'department_id' => $currentTimeline?->department_id ?? Department::first()->id,
                'position_id' => $currentTimeline?->position_id ?? Position::first()->id,
                'job_title' => $currentTimeline?->job_title ?? 'Nhân sự nghỉ việc',
                'manager_id' => $currentTimeline?->manager_id,
                'effective_date' => $resignedDate->format('Y-m-d'),
                'end_date' => null,
                'decision_number' => $data['decision_number'] ?? 'QĐ-TV-' . $employee->user_code,
                'decision_date' => $data['decision_date'] ?? $resignedDate->format('Y-m-d'),
                'notes' => ($data['notes'] ?? 'Thôi việc theo nguyện vọng cá nhân') . " (Thâm niên công tác: {$seniority})",
            ]);

            return $employee;
        });
    }

    /**
     * Ghi nhận sự kiện biến động vòng đời nhân sự (Effective Dating)
     */
    public function addCareerEvent(Employee $employee, array $data): EmployeeCareerTimeline
    {
        return DB::transaction(function () use ($employee, $data) {
            $effectiveDate = Carbon::parse($data['effective_date']);

            // Đóng mốc timeline hiện tại đang hiệu lực
            $currentTimeline = EmployeeCareerTimeline::where('employee_id', $employee->id)
                ->whereNull('end_date')
                ->where('effective_date', '<=', $effectiveDate)
                ->latest('effective_date')
                ->first();

            if ($currentTimeline) {
                $currentTimeline->update([
                    'end_date' => $effectiveDate->copy()->subDay()->format('Y-m-d'),
                ]);
            }

            // Tạo mốc sự kiện mới
            return EmployeeCareerTimeline::create([
                'employee_id' => $employee->id,
                'event_type' => $data['event_type'],
                'department_id' => $data['department_id'],
                'position_id' => $data['position_id'],
                'job_title' => $data['job_title'],
                'manager_id' => $data['manager_id'] ?? null,
                'effective_date' => $effectiveDate->format('Y-m-d'),
                'end_date' => null,
                'decision_number' => $data['decision_number'] ?? null,
                'decision_date' => $data['decision_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
