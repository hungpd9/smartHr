<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\PermissionAssignment;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Employee;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccessControlController extends Controller
{
    /**
     * Màn hình Ma Trận Phân Quyền & Ủy Quyền Hệ Thống
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'Chỉ Super Admin mới có quyền quản trị Ma trận Phân quyền & Ủy quyền.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);

        // 1. Danh sách Phân quyền đã cấu hình
        $assignments = PermissionAssignment::where('tenant_id', $tenantId)->get();

        // 2. Danh mục Modules trong hệ thống Smart-HR
        $modules = [
            [
                'code' => 'CORE_HR',
                'name' => 'Hồ sơ & Cơ cấu Tổ chức',
                'description' => 'Quản lý cây phòng ban, chức danh, hồ sơ nhân sự, thử việc, thôi việc và thâm niên',
                'icon' => 'User',
            ],
            [
                'code' => 'PAYROLL_SALARY',
                'name' => 'Lương & Cấu hình Thu nhập',
                'description' => 'Xem và thiết lập mức lương nhân viên, cấu hình lương, trích nộp bảo hiểm, thuế TNCN',
                'icon' => 'Money',
            ],
            [
                'code' => 'ATTENDANCE',
                'name' => 'Chấm công & Điểm danh',
                'description' => 'Xem bảng công, quẹt thẻ thời gian thực, chốt công tháng, xử lý đi muộn/về sớm',
                'icon' => 'Clock',
            ],
            [
                'code' => 'POLICY_WELFARE',
                'name' => 'Quy chế & Chế độ Phúc lợi',
                'description' => 'Thiết lập quy chế chấm công, danh mục phụ cấp, chế độ con nhỏ và phúc lợi doanh nghiệp',
                'icon' => 'Setting',
            ],
            [
                'code' => 'OFFICE_RESOURCES',
                'name' => 'Tài nguyên Văn phòng & Thiết bị',
                'description' => 'Quản lý cấp phát máy tính, bàn giao thiết bị, báo hỏng hỗ trợ IT, đề xuất tạm ứng & văn phòng phẩm',
                'icon' => 'Cpu',
            ],
        ];

        // 3. Danh mục Roles toàn cục
        $roles = [
            ['code' => 'SUPER_ADMIN', 'name' => 'Quản trị viên Cấp cao (Super Admin)', 'badge' => 'danger'],
            ['code' => 'HR_ADMIN', 'name' => 'Trưởng phòng / Quản trị HR', 'badge' => 'primary'],
            ['code' => 'ACCOUNTANT', 'name' => 'Kế toán trưởng / Kế toán Lương', 'badge' => 'warning'],
            ['code' => 'EMPLOYEE', 'name' => 'Nhân viên Tiêu chuẩn', 'badge' => 'info'],
        ];

        // 4. Danh sách Phòng ban & Chức vụ để phân quyền theo đối tượng
        $departments = Department::where('tenant_id', $tenantId)->with('currentVersion')->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->currentVersion?->name ?? $d->code,
            'level' => $d->level,
        ]);

        $positions = Position::where('tenant_id', $tenantId)->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'level' => $p->level,
        ]);

        // 5. Danh sách Nhân viên để ủy quyền cá nhân đặc biệt
        $employees = Employee::where('tenant_id', $tenantId)->where('status', 'ACTIVE')->get()->map(fn ($e) => [
            'id' => $e->id,
            'user_code' => $e->user_code,
            'full_name' => $e->full_name,
            'department' => $e->currentTimeline?->department?->currentVersion?->name ?? '---',
            'position' => $e->currentTimeline?->position?->name ?? '---',
        ]);

        return Inertia::render('AccessControl/Index', [
            'assignments' => $assignments,
            'modules' => $modules,
            'roles' => $roles,
            'departments' => $departments,
            'positions' => $positions,
            'employees' => $employees,
        ]);
    }

    /**
     * Cập nhật Ma Trận Phân Quyền
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'Chỉ Super Admin mới có quyền cập nhật Ma trận Phân quyền.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.subject_type' => 'required|in:ROLE,DEPARTMENT,POSITION,USER',
            'assignments.*.subject_id' => 'nullable|integer',
            'assignments.*.subject_role' => 'nullable|string',
            'assignments.*.module_code' => 'required|in:CORE_HR,PAYROLL_SALARY,ATTENDANCE,POLICY_WELFARE,OFFICE_RESOURCES',
            'assignments.*.can_view' => 'required|boolean',
            'assignments.*.can_manage' => 'required|boolean',
            'assignments.*.can_approve' => 'required|boolean',
            'assignments.*.notes' => 'nullable|string',
        ]);

        foreach ($data['assignments'] as $item) {
            PermissionAssignment::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'subject_type' => $item['subject_type'],
                    'subject_id' => $item['subject_id'] ?? null,
                    'subject_role' => $item['subject_role'] ?? null,
                    'module_code' => $item['module_code'],
                ],
                [
                    'can_view' => $item['can_view'],
                    'can_manage' => $item['can_manage'],
                    'can_approve' => $item['can_approve'],
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Đã lưu cấu hình Ma trận Phân quyền & Ủy quyền thành công.');
    }
}
