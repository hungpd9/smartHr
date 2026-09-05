<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\DepartmentVersion;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DepartmentService
{
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
                'status' => 'ACTIVE',
            ]);

            DepartmentVersion::create([
                'department_id' => $department->id,
                'name' => $data['name'],
                'mission_function' => $data['mission_function'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'effective_date' => $data['effective_date'] ?? Carbon::today()->format('Y-m-d'),
                'end_date' => null,
                'change_reason' => 'Thành lập phòng ban mới',
            ]);

            return $department;
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
            return DepartmentVersion::create([
                'department_id' => $department->id,
                'name' => $data['name'],
                'mission_function' => $data['mission_function'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'effective_date' => $effectiveDate->format('Y-m-d'),
                'end_date' => null,
                'change_reason' => $data['change_reason'] ?? 'Tái cơ cấu / Đổi tên phòng ban',
            ]);
        });
    }
}
