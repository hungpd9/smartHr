<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Services\EmployeeService;
use App\Modules\CoreHR\Requests\StoreEmployeeRequest;
use App\Modules\CoreHR\Requests\UpdateEmployeeRequest;
use App\Modules\CoreHR\Requests\OffboardEmployeeRequest;
use App\Modules\CoreHR\Requests\CreateCareerTimelineRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 1);
        $search = $request->query('search');
        $departmentId = $request->query('department_id');
        $status = $request->query('status');

        $query = Employee::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'currentTimeline.department.currentVersion',
                'currentTimeline.position',
                'children' => fn ($q) => $q->where('is_active', true),
                'certificates' => fn ($q) => $q->where('is_verified', true),
                'tenant',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('citizen_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($departmentId) {
            $query->whereHas('currentTimeline', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $now = Carbon::now();

        $employees = $query->latest('id')->paginate(15)->through(function ($emp) use ($now) {
            // Kiểm tra trạng thái phúc lợi & bảo hộ luật
            $isChildUnder12m = $emp->hasChildUnder12MonthsAt($now);
            $branch = $emp->currentTimeline?->department?->branch ?? Branch::first();
            $isPregnantRestricted = $branch ? $emp->isPregnantNightRestrictedAt($branch, $now) : false;

            return [
                'id' => $emp->id,
                'user_code' => $emp->user_code,
                'attendance_code' => $emp->attendance_code,
                'full_name' => $emp->full_name,
                'gender' => $emp->gender,
                'birth_date' => $emp->birth_date ? Carbon::parse($emp->birth_date)->format('Y-m-d') : null,
                'birth_date_display' => $emp->birth_date ? Carbon::parse($emp->birth_date)->format('d/m/Y') : '',
                'email' => $emp->email,
                'phone' => $emp->phone,
                'citizen_id' => $emp->citizen_id,
                'hometown' => $emp->hometown,
                'permanent_address' => $emp->permanent_address,
                'temporary_address' => $emp->temporary_address,
                'join_date' => $emp->join_date ? Carbon::parse($emp->join_date)->format('Y-m-d') : null,
                'join_date_display' => $emp->join_date ? Carbon::parse($emp->join_date)->format('d/m/Y') : '',
                'probation_end_date' => $emp->probation_end_date ? Carbon::parse($emp->probation_end_date)->format('Y-m-d') : null,
                'status' => $emp->status,
                'is_pregnant' => $emp->is_pregnant,
                'pregnancy_start_date' => $emp->pregnancy_start_date ? Carbon::parse($emp->pregnancy_start_date)->format('Y-m-d') : null,
                'pregnancy_weeks_at_start' => $emp->pregnancy_weeks_at_start,
                'expected_due_date' => $emp->expected_due_date ? Carbon::parse($emp->expected_due_date)->format('Y-m-d') : null,
                'children_count' => $emp->children->count(),
                'tax_dependents_count' => $emp->children->where('is_tax_dependent', true)->count(),
                'certificates_count' => $emp->certificates->count(),
                'seniority_text' => $emp->seniority_text,
                'resigned_date' => $emp->resigned_date ? Carbon::parse($emp->resigned_date)->format('d/m/Y') : null,
                'resignation_reason' => $emp->resignation_reason,
                'department_id' => $emp->currentTimeline?->department_id,
                'position_id' => $emp->currentTimeline?->position_id,
                'department_name' => $emp->currentTimeline?->department?->currentVersion?->name ?? 'Chưa phân bổ',
                'position_name' => $emp->currentTimeline?->position?->name ?? 'Chưa bổ nhiệm',
                'branch_name' => $emp->currentTimeline?->department?->branch?->name ?? 'Hội sở',
                'is_child_under_12m' => $isChildUnder12m,
                'is_pregnant_restricted' => $isPregnantRestricted,
            ];
        });

        $departments = Department::with(['currentVersion.manager', 'branch'])
            ->orderBy('level')
            ->orderBy('order')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->currentVersion?->name ?? $d->code,
                'mission_function' => $d->currentVersion?->mission_function ?? '',
                'manager_id' => $d->currentVersion?->manager_id,
                'manager_name' => $d->currentVersion?->manager?->full_name,
                'level' => $d->level,
                'order' => $d->order,
                'parent_id' => $d->parent_id,
                'branch_id' => $d->branch_id,
                'branch_name' => $d->branch?->name ?? '',
                'status' => $d->status,
            ]);

        // Xây dựng sơ đồ cây phân cấp Org Chart (Tree Structure)
        $orgTree = $this->buildOrgTree();

        $positions = Position::orderBy('level')->get(['id', 'code', 'name', 'level', 'department_id', 'allowance_amount', 'bonus_one_time', 'description', 'is_active']);
        $branches = Branch::where('is_active', true)->get();

        $allEmployeesShort = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->select(['id', 'user_code', 'full_name'])
            ->orderBy('full_name')
            ->get();

        return Inertia::render('CoreHR/Index', [
            'employees' => $employees,
            'departments' => $departments,
            'orgTree' => $orgTree,
            'positions' => $positions,
            'branches' => $branches,
            'allEmployeesShort' => $allEmployeesShort,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'status' => $status,
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employeeService->createEmployee($request->validated());

        return redirect()->route('core-hr.index')->with('success', 'Thêm mới nhân sự và kích hoạt Timeline Onboarding thành công!');
    }

    public function update(UpdateEmployeeRequest $request, int $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        $this->employeeService->updateEmployee($employee, $request->validated());

        return redirect()->back()->with('success', "Cập nhật hồ sơ nhân sự {$employee->full_name} thành công!");
    }

    public function offboard(OffboardEmployeeRequest $request, int $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        $this->employeeService->offboardEmployee($employee, $request->validated());

        return redirect()->back()->with('success', "Quy trình thôi việc (Offboarding) cho nhân sự {$employee->full_name} đã hoàn tất!");
    }

    /**
     * Xây dựng sơ đồ cây phân cấp tổ chức (Org Tree Graph)
     */
    protected function buildOrgTree(): array
    {
        $rootDepartments = Department::with([
            'currentVersion',
            'branch',
            'children.currentVersion',
            'children.children.currentVersion',
            'positions',
        ])
        ->whereNull('parent_id')
        ->where('status', 'ACTIVE')
        ->orderBy('order')
        ->get();

        $formatNode = function ($dept) use (&$formatNode) {
            // Đếm số lượng nhân viên hiện tại trong phòng ban này
            $empCount = Employee::whereHas('currentTimeline', function ($q) use ($dept) {
                $q->where('department_id', $dept->id);
            })->where('status', '!=', 'TERMINATED')->count();

            // Lấy thông tin Trưởng bộ phận
            $managerId = $dept->currentVersion?->manager_id;
            $manager = $managerId ? Employee::find($managerId, ['id', 'full_name', 'user_code']) : null;

            return [
                'id' => $dept->id,
                'code' => $dept->code,
                'name' => $dept->currentVersion?->name ?? $dept->code,
                'level' => $dept->level,
                'level_label' => match ($dept->level) {
                    1 => 'Khối chức năng',
                    2 => 'Phòng ban',
                    3 => 'Đội nhóm / Team',
                    default => 'Đơn vị',
                },
                'branch_name' => $dept->branch?->name,
                'mission_function' => $dept->currentVersion?->mission_function,
                'manager' => $manager ? [
                    'id' => $manager->id,
                    'full_name' => $manager->full_name,
                    'user_code' => $manager->user_code,
                ] : null,
                'employee_count' => $empCount,
                'positions' => $dept->positions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'level' => $p->level,
                    'allowance_amount' => (float) $p->allowance_amount,
                ]),
                'children' => $dept->children->map($formatNode)->values(),
            ];
        };

        return $rootDepartments->map($formatNode)->values()->toArray();
    }

    public function show(int $id): Response
    {
        $tenantId = (int) (request()->user()?->tenant_id ?? 1);
        $employee = Employee::where('tenant_id', $tenantId)
            ->with([
                'careerTimelines.department.currentVersion',
                'careerTimelines.position',
                'careerTimelines.manager',
                'contracts',
            ])->findOrFail($id);

        $now = Carbon::now();
        $isChildUnder12m = $employee->hasChildUnder12MonthsAt($now);
        $branch = $employee->currentTimeline?->department?->branch ?? Branch::first();
        $isPregnantRestricted = $branch ? $employee->isPregnantNightRestrictedAt($branch, $now) : false;

        $timelines = $employee->careerTimelines->sortByDesc('effective_date')->values()->map(function ($t) {
            return [
                'id' => $t->id,
                'event_type' => $t->event_type,
                'job_title' => $t->job_title,
                'position_name' => $t->position?->name,
                'department_name' => $t->department?->currentVersion?->name,
                'manager_name' => $t->manager?->full_name,
                'effective_date' => Carbon::parse($t->effective_date)->format('d/m/Y'),
                'end_date' => $t->end_date ? Carbon::parse($t->end_date)->format('d/m/Y') : 'Hiện tại',
                'decision_number' => $t->decision_number,
                'decision_date' => $t->decision_date ? Carbon::parse($t->decision_date)->format('d/m/Y') : null,
                'notes' => $t->notes,
                'is_active' => $t->end_date === null,
            ];
        });

        $contracts = $employee->contracts->map(function ($c) {
            return [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'contract_type' => $c->contract_type,
                'sign_date' => Carbon::parse($c->sign_date)->format('d/m/Y'),
                'start_date' => Carbon::parse($c->start_date)->format('d/m/Y'),
                'end_date' => $c->end_date ? Carbon::parse($c->end_date)->format('d/m/Y') : 'Không xác định',
                'status' => $c->status,
            ];
        });

        $departments = Department::with('currentVersion')
            ->where('status', 'ACTIVE')
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->currentVersion?->name ?? $d->code]);

        $positions = Position::where('is_active', true)->get(['id', 'code', 'name']);
        $managers = Employee::where('id', '!=', $employee->id)->where('status', '!=', 'TERMINATED')->get(['id', 'full_name', 'user_code']);

        return Inertia::render('CoreHR/Show', [
            'employee' => [
                'id' => $employee->id,
                'user_code' => $employee->user_code,
                'full_name' => $employee->full_name,
                'gender' => $employee->gender,
                'birth_date' => Carbon::parse($employee->birth_date)->format('d/m/Y'),
                'email' => $employee->email,
                'phone' => $employee->phone,
                'join_date' => Carbon::parse($employee->join_date)->format('d/m/Y'),
                'status' => $employee->status,
                'is_pregnant' => $employee->is_pregnant,
                'pregnancy_start_date' => $employee->pregnancy_start_date ? Carbon::parse($employee->pregnancy_start_date)->format('d/m/Y') : null,
                'child_birth_date' => $employee->child_birth_date ? Carbon::parse($employee->child_birth_date)->format('d/m/Y') : null,
                'is_child_under_12m' => $isChildUnder12m,
                'is_pregnant_restricted' => $isPregnantRestricted,
            ],
            'timelines' => $timelines,
            'contracts' => $contracts,
            'departments' => $departments,
            'positions' => $positions,
            'managers' => $managers,
        ]);
    }

    public function addTimelineEvent(int $id, CreateCareerTimelineRequest $request): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        $this->employeeService->addCareerEvent($employee, $request->validated());

        return redirect()->back()->with('success', 'Ghi nhận biến động sự nghiệp (Effective Dating) thành công!');
    }
}
