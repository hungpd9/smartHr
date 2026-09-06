<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\DepartmentVersion;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Compliance\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class DepartmentService
{
    public function __construct(
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Tạo mới phòng ban cùng phiên bản chức năng nhiệm vụ đầu tiên
     */
    public function createDepartment(array $data): Department
    {
        return DB::transaction(function () use ($data) {
            $department = Department::create([
                'tenant_id' => $data['tenant_id'] ?? 1,
                'branch_id' => $data['branch_id'],
                'code' => $data['code'],
                'parent_id' => $data['parent_id'] ?? null,
                'level' => $data['level'] ?? 1,
                'order' => $data['order'] ?? 0,
                'status' => $data['status'] ?? 'ACTIVE',
            ]);

            $version = DepartmentVersion::create([
                'department_id' => $department->id,
                'name' => $data['name'],
                'mission_function' => $data['mission_function'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'effective_date' => $data['effective_date'] ?? Carbon::today()->format('Y-m-d'),
                'end_date' => null,
                'change_reason' => 'Thành lập phòng ban mới',
            ]);

            $this->auditTrailService->log(
                (int) $department->tenant_id,
                'CREATE',
                $department,
                null,
                array_merge($department->toArray(), ['name' => $version->name])
            );

            return $department;
        });
    }

    /**
     * Cập nhật thông tin phòng ban & phiên bản hiện hành
     */
    public function updateDepartment(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data) {
            $oldValues = array_merge($department->toArray(), [
                'name' => $department->currentVersion?->name,
                'mission_function' => $department->currentVersion?->mission_function,
                'manager_id' => $department->currentVersion?->manager_id,
            ]);

            // Cập nhật bảng departments
            $department->update([
                'branch_id' => $data['branch_id'] ?? $department->branch_id,
                'code' => $data['code'] ?? $department->code,
                'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $department->parent_id,
                'level' => $data['level'] ?? $department->level,
                'order' => $data['order'] ?? $department->order,
                'status' => $data['status'] ?? $department->status,
            ]);

            // Cập nhật thông tin tên / chức năng trong phiên bản hiện hành nếu có
            if (isset($data['name']) || isset($data['mission_function']) || array_key_exists('manager_id', $data)) {
                $currentVersion = $department->currentVersion;
                if ($currentVersion) {
                    $currentVersion->update(array_filter([
                        'name' => $data['name'] ?? $currentVersion->name,
                        'mission_function' => $data['mission_function'] ?? $currentVersion->mission_function,
                        'manager_id' => array_key_exists('manager_id', $data) ? $data['manager_id'] : $currentVersion->manager_id,
                    ], fn($val) => $val !== null));
                } else {
                    DepartmentVersion::create([
                        'department_id' => $department->id,
                        'name' => $data['name'] ?? $department->code,
                        'mission_function' => $data['mission_function'] ?? null,
                        'manager_id' => $data['manager_id'] ?? null,
                        'effective_date' => Carbon::today()->format('Y-m-d'),
                        'end_date' => null,
                        'change_reason' => 'Cập nhật thông tin phòng ban',
                    ]);
                }
            }

            $newValues = array_merge($department->fresh()->toArray(), [
                'name' => $department->fresh()->currentVersion?->name,
                'mission_function' => $department->fresh()->currentVersion?->mission_function,
                'manager_id' => $department->fresh()->currentVersion?->manager_id,
            ]);

            $this->auditTrailService->log(
                (int) $department->tenant_id,
                'UPDATE',
                $department,
                $oldValues,
                $newValues
            );

            return $department;
        });
    }

    /**
     * Xóa phòng ban có kiểm tra toàn vẹn dữ liệu
     */
    public function deleteDepartment(Department $department): void
    {
        // 1. Kiểm tra xem có nhân viên đang trực thuộc hay không
        $hasActiveEmployees = EmployeeCareerTimeline::where('department_id', $department->id)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', Carbon::today());
            })
            ->exists();

        if ($hasActiveEmployees) {
            throw ValidationException::withMessages([
                'department' => [
                    "Không thể xóa phòng ban '{$department->name}' vì đang có nhân sự trực thuộc. " .
                    "Vui lòng điều chuyển nhân sự sang đơn vị khác trước hoặc chuyển trạng thái sang Ngừng hoạt động (INACTIVE)."
                ],
            ]);
        }

        // 2. Kiểm tra xem có phòng ban con trực thuộc hay không
        if ($department->children()->exists()) {
            throw ValidationException::withMessages([
                'department' => [
                    "Không thể xóa phòng ban '{$department->name}' vì đang có các đơn vị cấp dưới trực thuộc. " .
                    "Vui lòng điều chuyển các đơn vị cấp dưới trước khi giải thể."
                ],
            ]);
        }

        DB::transaction(function () use ($department) {
            $oldValues = array_merge($department->toArray(), ['name' => $department->name]);

            // Xóa các phiên bản lịch sử
            $department->versions()->delete();
            $department->delete();

            $this->auditTrailService->log(
                (int) $department->tenant_id,
                'DELETE',
                $department,
                $oldValues,
                null
            );
        });
    }

    /**
     * Ban hành phiên bản phòng ban mới (Lịch sử đổi tên / tái cơ cấu nhiệm vụ)
     */
    public function publishNewVersion(Department $department, array $data): DepartmentVersion
    {
        return DB::transaction(function () use ($department, $data) {
            $effectiveDate = Carbon::parse($data['effective_date']);

            // Đóng phiên bản cũ
            $currentVersion = DepartmentVersion::where('department_id', $department->id)
                ->whereNull('end_date')
                ->where('effective_date', '<=', $effectiveDate)
                ->latest('effective_date')
                ->first();

            if ($currentVersion) {
                $currentVersion->update([
                    'end_date' => $effectiveDate->copy()->subDay()->format('Y-m-d'),
                ]);
            }

            // Tạo phiên bản mới
            $version = DepartmentVersion::create([
                'department_id' => $department->id,
                'name' => $data['name'],
                'mission_function' => $data['mission_function'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'effective_date' => $effectiveDate->format('Y-m-d'),
                'end_date' => null,
                'change_reason' => $data['change_reason'] ?? 'Tái cơ cấu / Đổi tên phòng ban',
            ]);

            $this->auditTrailService->log(
                (int) $department->tenant_id,
                'PUBLISH_VERSION',
                $department,
                ['previous_version_name' => $currentVersion?->name],
                ['new_version_name' => $version->name, 'effective_date' => $version->effective_date]
            );

            return $version;
        });
    }
}
