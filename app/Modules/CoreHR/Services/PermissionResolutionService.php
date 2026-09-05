<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Models\User;
use App\Enums\UserRole;
use App\Modules\CoreHR\Models\PermissionAssignment;
use App\Modules\CoreHR\Models\Employee;

class PermissionResolutionService
{
    /**
     * Cache quyền hạn theo request (in-memory request memoization)
     */
    protected array $resolvedCache = [];

    /**
     * Kiểm tra người dùng có quyền truy cập vào Module tương ứng hay không
     * Thuật toán phân giải 4 tầng ưu tiên:
     * 1. Super Admin (Bypass toàn bộ)
     * 2. Ngoại lệ Cá nhân (USER)
     * 3. Chức vụ (POSITION)
     * 4. Phòng ban (DEPARTMENT)
     * 5. Vai trò Toàn cục (ROLE)
     * 6. Fallback mặc định theo vai trò chuẩn
     *
     * @param User $user
     * @param string $moduleCode CORE_HR | PAYROLL_SALARY | ATTENDANCE | POLICY_WELFARE | OFFICE_RESOURCES
     * @param string $permissionType 'view' | 'manage' | 'approve'
     * @return bool
     */
    public function canAccess(User $user, string $moduleCode, string $permissionType = 'view'): bool
    {
        // 0. Super Admin có toàn quyền truy cập không điều kiện
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $field = match ($permissionType) {
            'manage' => 'can_manage',
            'approve' => 'can_approve',
            default => 'can_view',
        };

        // Cache memoization trong request
        $cacheKey = "{$user->id}:{$moduleCode}:{$field}";
        if (isset($this->resolvedCache[$cacheKey])) {
            return $this->resolvedCache[$cacheKey];
        }

        // Tìm hồ sơ nhân viên liên kết (nếu có)
        $employee = Employee::where('tenant_id', $tenantId)
            ->where('user_code', $user->employee_code ?? $user->email)
            ->with('currentTimeline')
            ->first();

        // 1. Tầng 1: Ngoại lệ cá nhân (USER)
        if ($employee) {
            $userPerm = PermissionAssignment::where('tenant_id', $tenantId)
                ->where('subject_type', 'USER')
                ->where('subject_id', $employee->id)
                ->where('module_code', $moduleCode)
                ->first();

            if ($userPerm !== null) {
                return $this->resolvedCache[$cacheKey] = (bool) $userPerm->{$field};
            }

            $timeline = $employee->currentTimeline;

            // 2. Tầng 2: Chức vụ / Vị trí (POSITION)
            if ($timeline && $timeline->position_id) {
                $posPerm = PermissionAssignment::where('tenant_id', $tenantId)
                    ->where('subject_type', 'POSITION')
                    ->where('subject_id', $timeline->position_id)
                    ->where('module_code', $moduleCode)
                    ->first();

                if ($posPerm !== null) {
                    return $this->resolvedCache[$cacheKey] = (bool) $posPerm->{$field};
                }
            }

            // 3. Tầng 3: Phòng ban (DEPARTMENT)
            if ($timeline && $timeline->department_id) {
                $deptPerm = PermissionAssignment::where('tenant_id', $tenantId)
                    ->where('subject_type', 'DEPARTMENT')
                    ->where('subject_id', $timeline->department_id)
                    ->where('module_code', $moduleCode)
                    ->first();

                if ($deptPerm !== null) {
                    return $this->resolvedCache[$cacheKey] = (bool) $deptPerm->{$field};
                }
            }
        }

        // 4. Tầng 4: Vai trò toàn cục (ROLE)
        $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $rolePerm = PermissionAssignment::where('tenant_id', $tenantId)
            ->where('subject_type', 'ROLE')
            ->where('subject_role', $roleValue)
            ->where('module_code', $moduleCode)
            ->first();

        if ($rolePerm !== null) {
            return $this->resolvedCache[$cacheKey] = (bool) $rolePerm->{$field};
        }

        // 5. Tầng 5: Fallback mặc định theo vai trò tiêu chuẩn
        $defaultAllowed = $this->resolveStandardRoleFallback($user->role, $moduleCode, $field);
        return $this->resolvedCache[$cacheKey] = $defaultAllowed;
    }

    /**
     * Quyền mặc định chuẩn hóa theo vai trò cơ sở
     */
    protected function resolveStandardRoleFallback(UserRole|string $role, string $moduleCode, string $field): bool
    {
        $roleStr = $role instanceof UserRole ? $role->value : (string) $role;

        switch ($roleStr) {
            case 'HR_ADMIN':
                if (in_array($moduleCode, ['CORE_HR', 'ATTENDANCE', 'POLICY_WELFARE', 'OFFICE_RESOURCES'], true)) {
                    return true;
                }
                if ($moduleCode === 'PAYROLL_SALARY') {
                    return $field === 'can_view'; // Mặc định chỉ xem, không sửa lương
                }
                return false;

            case 'ACCOUNTANT':
                if ($moduleCode === 'PAYROLL_SALARY') {
                    return true; // Kế toán toàn quyền lương
                }
                if (in_array($moduleCode, ['ATTENDANCE', 'POLICY_WELFARE'], true)) {
                    return in_array($field, ['can_view', 'can_manage'], true);
                }
                return false;

            case 'EMPLOYEE':
                if (in_array($moduleCode, ['ATTENDANCE', 'POLICY_WELFARE'], true)) {
                    return $field === 'can_view';
                }
                return false;

            default:
                return false;
        }
    }
}
