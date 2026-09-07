<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Workflow\Models\WorkflowDefinition;
use Carbon\Carbon;

class WorkflowRoutingService
{
    /**
     * Phân giải chuỗi người duyệt (Approval Chain) và tạo Snapshot tại thời điểm nộp đơn
     */
    public function resolveApprovalChain(
        int $tenantId,
        Employee $requester,
        string $requestType,
        array $payload = []
    ): array {
        // 1. Lấy cấu hình quy trình duyệt của tenant, nếu chưa có thì khởi tạo mặc định
        $definition = WorkflowDefinition::where('tenant_id', $tenantId)
            ->where('request_type', $requestType)
            ->where('is_active', true)
            ->first();

        if (!$definition) {
            $this->seedDefaultDefinitions($tenantId);
            $definition = WorkflowDefinition::where('tenant_id', $tenantId)
                ->where('request_type', $requestType)
                ->where('is_active', true)
                ->first();
        }

        $stepsConfig = $definition?->steps_config ?? $this->getDefaultStepsConfig($requestType);
        $totalLevels = $definition?->total_levels ?? count($stepsConfig);

        // Kiểm tra điều kiện số ngày để giảm cấp duyệt nếu có (Zero-Hardcode Rule)
        if (!empty($definition?->condition_rules)) {
            $minDaysLevel2 = $definition->condition_rules['min_days_for_level_2'] ?? null;
            $requestedDays = (float)($payload['days'] ?? $payload['duration_days'] ?? 1);
            if ($minDaysLevel2 !== null && $requestedDays < (float)$minDaysLevel2 && $totalLevels > 1) {
                $totalLevels = 1;
                $stepsConfig = array_slice($stepsConfig, 0, 1);
            }
        }

        // 2. Lấy thông tin cây sơ đồ tổ chức của nhân viên tại thời điểm hiện tại
        $requester->loadMissing([
            'timelines' => function ($q) {
                $q->where('effective_date', '<=', Carbon::today())
                  ->where(function ($sub) {
                      $sub->whereNull('end_date')->orWhere('end_date', '>=', Carbon::today());
                  })
                  ->with(['department.currentVersion', 'manager']);
            }
        ]);

        $currentTimeline = $requester->timelines->first();
        $directManager = $currentTimeline?->manager;
        $deptManager = $currentTimeline?->department?->currentVersion?->manager;

        // Fallback người duyệt HR / Super Admin
        $hrAdminEmployee = $this->resolveHrAdminEmployee($tenantId, $requester->id);

        $snapshot = [];
        for ($level = 1; $level <= $totalLevels; $level++) {
            $config = $stepsConfig[$level - 1] ?? null;
            $roleType = $config['role_type'] ?? ($level === 1 ? 'DIRECT_MANAGER' : 'HR_ADMIN');
            $stepTitle = $config['title'] ?? "Cấp duyệt {$level}";

            $approver = null;

            if ($roleType === 'DIRECT_MANAGER') {
                $approver = $directManager ?? $deptManager ?? $hrAdminEmployee;
            } elseif ($roleType === 'DEPARTMENT_HEAD') {
                $approver = $deptManager ?? $directManager ?? $hrAdminEmployee;
            } elseif ($roleType === 'HR_ADMIN' || $roleType === 'GENERAL_DIRECTOR') {
                $approver = $hrAdminEmployee ?? $deptManager ?? $directManager;
            }

            // Nếu người duyệt trùng với chính nhân viên nộp đơn (tự nộp đơn), chuyển lên cấp trên
            if ($approver && $approver->id === $requester->id) {
                if ($roleType === 'DIRECT_MANAGER' && $deptManager && $deptManager->id !== $requester->id) {
                    $approver = $deptManager;
                } else {
                    $approver = $hrAdminEmployee;
                }
            }

            $snapshot[] = [
                'level' => $level,
                'role_type' => $roleType,
                'title' => $stepTitle,
                'approver_id' => $approver?->id,
                'approver_name' => $approver?->full_name ?? 'Ban Quản Trị Hệ Thống',
                'approver_code' => $approver?->user_code ?? 'ADMIN',
                'status' => $level === 1 ? 'PENDING' : 'WAITING',
                'action_at' => null,
                'comment' => null,
            ];
        }

        return $snapshot;
    }

    /**
     * Tìm nhân sự có vai trò HR_ADMIN hoặc SUPER_ADMIN để đóng vai trò cấp duyệt HR
     */
    protected function resolveHrAdminEmployee(int $tenantId, int $excludeEmployeeId): ?Employee
    {
        $adminUser = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['HR_ADMIN', 'SUPER_ADMIN', 'ADMIN'])
            ->first();

        if (!$adminUser) {
            return Employee::where('tenant_id', $tenantId)
                ->where('id', '!=', $excludeEmployeeId)
                ->first();
        }

        // Tìm employee gắn với email của user này hoặc nhân viên đầu tiên
        return Employee::where('tenant_id', $tenantId)
            ->where('email', $adminUser->email)
            ->first() ?? Employee::where('tenant_id', $tenantId)
                ->where('id', '!=', $excludeEmployeeId)
                ->first();
    }

    /**
     * Cấu hình các bước duyệt mặc định theo loại đơn
     */
    protected function getDefaultStepsConfig(string $requestType): array
    {
        return match ($requestType) {
            'LEAVE', 'OVERTIME', 'NIGHT_SHIFT' => [
                ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý trực tiếp'],
                ['level' => 2, 'role_type' => 'HR_ADMIN', 'title' => 'Phòng Nhân sự'],
            ],
            default => [
                ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý trực tiếp'],
            ],
        };
    }

    /**
     * Tự động khởi tạo quy trình mẫu cho doanh nghiệp
     */
    public function seedDefaultDefinitions(int $tenantId): void
    {
        $defaultList = [
            [
                'request_type' => 'LEAVE',
                'name' => 'Quy trình Duyệt Nghỉ Phép',
                'description' => 'Phê duyệt 2 cấp: Quản lý trực tiếp phê duyệt và Phòng Nhân sự xác nhận trừ quỹ phép.',
                'total_levels' => 2,
                'steps_config' => [
                    ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý trực tiếp'],
                    ['level' => 2, 'role_type' => 'HR_ADMIN', 'title' => 'Phòng Nhân sự'],
                ],
                'condition_rules' => ['min_days_for_level_2' => 3],
            ],
            [
                'request_type' => 'OVERTIME',
                'name' => 'Quy trình Đăng ký Làm Thêm Giờ (OT)',
                'description' => 'Phê duyệt 2 cấp tuân thủ Điều 107 BLLĐ: Quản lý ca trực duyệt và Nhân sự kiểm soát trần 40h/tháng.',
                'total_levels' => 2,
                'steps_config' => [
                    ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý ca trực'],
                    ['level' => 2, 'role_type' => 'HR_ADMIN', 'title' => 'Phòng Nhân sự'],
                ],
                'condition_rules' => null,
            ],
            [
                'request_type' => 'NIGHT_SHIFT',
                'name' => 'Đơn Thỏa thuận Làm Đêm / OT Con Nhỏ (Đ.137 BLLĐ)',
                'description' => 'Dành riêng cho lao động nuôi con < 12 tháng tự nguyện đăng ký ca đêm hoặc làm thêm giờ có văn bản thỏa thuận.',
                'total_levels' => 2,
                'steps_config' => [
                    ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý trực tiếp'],
                    ['level' => 2, 'role_type' => 'HR_ADMIN', 'title' => 'Phòng Nhân sự đối soát hồ sơ'],
                ],
                'condition_rules' => null,
            ],
            [
                'request_type' => 'TIMESHEET_EDIT',
                'name' => 'Quy trình Giải trình / Bổ sung Giờ Chấm Công',
                'description' => 'Phê duyệt 1 cấp: Quản lý trực tiếp xác nhận đi công tác hoặc quên chấm vân tay.',
                'total_levels' => 1,
                'steps_config' => [
                    ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý trực tiếp'],
                ],
                'condition_rules' => null,
            ],
            [
                'request_type' => 'SHIFT_SWAP',
                'name' => 'Quy trình Xin Đổi Ca Làm Việc',
                'description' => 'Phê duyệt 1 cấp: Quản lý ca phê duyệt hoán đổi ca làm việc giữa 2 nhân sự.',
                'total_levels' => 1,
                'steps_config' => [
                    ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Quản lý ca'],
                ],
                'condition_rules' => null,
            ],
        ];

        foreach ($defaultList as $item) {
            WorkflowDefinition::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'request_type' => $item['request_type'],
                ],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'total_levels' => $item['total_levels'],
                    'steps_config' => $item['steps_config'],
                    'condition_rules' => $item['condition_rules'],
                    'is_active' => true,
                ]
            );
        }
    }
}
