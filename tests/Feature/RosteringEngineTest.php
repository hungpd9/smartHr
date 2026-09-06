<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use App\Models\User;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\DepartmentVersion;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeChild;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Models\ShiftSwapRequest;
use App\Modules\Rostering\Services\RosteringConstraintValidator;
use App\Modules\Rostering\Solvers\AutoRosteringSolverService;
use App\Modules\Workflow\Models\ApprovalRequest;

class RosteringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected Department $department;
    protected Position $position;
    protected User $adminUser;
    protected Employee $employee1;
    protected Employee $employee2;
    protected Shift $morningShift;
    protected Shift $eveningShift;
    protected Shift $nightShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'code' => 'TENANT_ROSTER',
            'name' => 'Công ty Cổ phần Bán lẻ Roster',
            'domain' => 'roster.test',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_STORE',
            'name' => 'Chi nhánh Siêu thị Hà Nội',
            'secret_key' => 'secret_roster_123',
            'is_remote_or_island' => false,
            'is_active' => true,
        ]);

        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'code' => 'DEPT_RETAIL',
            'status' => 'ACTIVE',
        ]);

        DepartmentVersion::create([
            'department_id' => $this->department->id,
            'name' => 'Khối Vận hành Siêu thị',
            'effective_date' => '2025-01-01',
        ]);

        $this->position = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'code' => 'STAFF_RETAIL',
            'name' => 'Nhân viên Siêu thị',
            'level' => 1,
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'HR Manager',
            'email' => 'hr@roster.test',
            'password' => bcrypt('password'),
            'role' => 'HR_ADMIN',
            'allowed_modules' => ['rostering', 'attendance', 'core_hr'],
        ]);

        $this->employee1 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_R01',
            'full_name' => 'Nguyễn Văn Nam',
            'gender' => 'MALE',
            'birth_date' => '1992-05-10',
            'email' => 'nam.nv@roster.test',
            'join_date' => '2023-01-01',
            'status' => 'OFFICIAL',
        ]);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->employee1->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Nhân viên Bán hàng',
            'effective_date' => '2023-01-01',
        ]);

        $this->employee2 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_R02',
            'full_name' => 'Trần Thị Thu',
            'gender' => 'FEMALE',
            'birth_date' => '1995-08-20',
            'email' => 'thu.tt@roster.test',
            'join_date' => '2024-01-01',
            'status' => 'OFFICIAL',
        ]);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->employee2->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Nhân viên Thu ngân',
            'effective_date' => '2024-01-01',
        ]);

        // Tạo 3 ca chuẩn
        $this->morningShift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CA_SANG',
            'name' => 'Ca Sáng (06:00 - 14:00)',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'break_minutes' => 30,
            'is_night_shift' => false,
            'is_active' => true,
        ]);

        $this->eveningShift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CA_CHIEU',
            'name' => 'Ca Chiều (14:00 - 22:00)',
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
            'break_minutes' => 30,
            'is_night_shift' => false,
            'is_active' => true,
        ]);

        $this->nightShift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CA_DEM',
            'name' => 'Ca Đêm (22:00 - 06:00)',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_minutes' => 60,
            'is_night_shift' => true,
            'is_active' => true,
        ]);
    }

    public function test_shifts_index_renders_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/rosters/shifts');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Rostering/Shifts/Index')
            ->has('shifts', 3)
        );
    }

    public function test_can_store_and_update_shift(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/rosters/shifts', [
            'code' => 'CA_GAY_FNB',
            'name' => 'Ca Gãy Nhà Hàng',
            'start_time' => '10:00',
            'end_time' => '21:00',
            'break_minutes' => 120,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shifts', [
            'code' => 'CA_GAY_FNB',
            'name' => 'Ca Gãy Nhà Hàng',
            'break_minutes' => 120,
        ]);

        $shift = Shift::where('code', 'CA_GAY_FNB')->first();
        $this->assertNotNull($shift);
        // (11 tiếng - 2 tiếng nghỉ) = 9 tiếng
        $this->assertEquals(9.0, $shift->work_hours);
    }

    public function test_roster_matrix_renders_with_grid(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/rosters');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Rostering/Index')
            ->has('matrix')
            ->has('dates')
            ->has('shifts')
        );
    }

    public function test_hc01_enforcement_rest_hours_between_shifts(): void
    {
        // 1. Phân ca chiều kết thúc lúc 22:00 ngày 2026-09-10
        Roster::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->eveningShift->id,
            'roster_date' => '2026-09-10',
            'status' => 'SCHEDULED',
        ]);

        // 2. Thử phân ca sáng ngày 2026-09-11 bắt đầu lúc 06:00 (khoảng nghỉ chỉ 8h < 12h)
        $validator = app(RosteringConstraintValidator::class);
        $result = $validator->validateShiftAssignment(
            $this->employee1->id,
            $this->morningShift->id,
            '2026-09-11'
        );

        $this->assertFalse($result['is_valid']);
        $this->assertTrue($result['is_hard_violation']);
        $this->assertNotEmpty($result['hard_violations']);
        $this->assertStringContainsString('Điều 110 BLLĐ', $result['hard_violations'][0]);
    }

    public function test_hc05_child_under_12m_night_shift_blocked_and_consent_unlocked(): void
    {
        // 1. Gán con nhỏ 7 tháng tuổi cho employee2 (Trần Thị Thu)
        EmployeeChild::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'full_name' => 'Bé Trần Bảo An',
            'birth_date' => Carbon::now()->subMonths(7)->toDateString(),
            'is_tax_dependent' => true,
            'is_active' => true,
        ]);

        $validator = app(RosteringConstraintValidator::class);

        // 2. Thử phân ca đêm khi CHƯA CÓ đơn thỏa thuận tự nguyện
        $checkBlocked = $validator->validateShiftAssignment(
            $this->employee2->id,
            $this->nightShift->id,
            Carbon::now()->toDateString()
        );

        $this->assertFalse($checkBlocked['is_valid']);
        $this->assertTrue($checkBlocked['is_hard_violation']);
        $this->assertStringContainsString('Điều 137 Khoản 1 Điểm b BLLĐ', $checkBlocked['hard_violations'][0]);

        // 3. Nhân viên nộp đơn thỏa thuận tự nguyện được phê duyệt
        ApprovalRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'request_type' => 'NIGHT_SHIFT',
            'form_payload' => ['reason' => 'Tự nguyện làm thêm ca đêm để chăm lo kinh tế'],
            'is_voluntary_consent' => true,
            'status' => 'APPROVED',
            'snapshot_approvers' => [],
        ]);

        // 4. Kiểm tra lại: Ràng buộc phải được mở khóa thành công!
        $checkUnlocked = $validator->validateShiftAssignment(
            $this->employee2->id,
            $this->nightShift->id,
            Carbon::now()->toDateString()
        );

        $this->assertTrue($checkUnlocked['is_valid']);
        $this->assertFalse($checkUnlocked['is_hard_violation']);
        $this->assertNotEmpty($checkUnlocked['notes']);
        $this->assertStringContainsString('ĐÃ CÓ văn bản đồng thuận tự nguyện', $checkUnlocked['notes'][0]);
    }

    public function test_auto_rostering_solver_fairness_and_compliance(): void
    {
        $solver = app(AutoRosteringSolverService::class);

        $startDate = '2026-09-01';
        $endDate = '2026-09-07'; // 7 ngày

        $result = $solver->solve(
            $this->tenant->id,
            $startDate,
            $endDate,
            'FAIRNESS',
            [$this->morningShift->id, $this->nightShift->id]
        );

        $this->assertGreaterThan(0, $result['total_shifts_scheduled']);
        $this->assertGreaterThan(0, $result['total_rest_days']);
        $this->assertEquals(2, $result['employees_count']);

        // Kiểm tra xem nhân viên có ít nhất 1 ngày nghỉ trong tuần (HC-02)
        $emp1RosterCount = Roster::where('employee_id', $this->employee1->id)
            ->whereBetween('roster_date', [$startDate, $endDate])
            ->count();

        $this->assertLessThanOrEqual(6, $emp1RosterCount, 'Nhân viên phải có ít nhất 1 ngày nghỉ trong tuần theo Điều 111 BLLĐ');
    }

    public function test_shift_swap_workflow_and_approval(): void
    {
        // Tạo 2 phân ca cho 2 nhân viên
        $roster1 = Roster::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->morningShift->id,
            'roster_date' => '2026-09-15',
            'status' => 'SCHEDULED',
        ]);

        $roster2 = Roster::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'shift_id' => $this->eveningShift->id,
            'roster_date' => '2026-09-15',
            'status' => 'SCHEDULED',
        ]);

        // Tạo yêu cầu đổi ca
        $swapRequest = ShiftSwapRequest::create([
            'requester_id' => $this->employee1->id,
            'target_employee_id' => $this->employee2->id,
            'requester_roster_id' => $roster1->id,
            'target_roster_id' => $roster2->id,
            'swap_type' => 'SWAP',
            'reason' => 'Đổi ca đi khám bệnh',
            'is_legal_compliant' => true,
            'status' => 'PENDING_MANAGER',
        ]);

        // Quản lý phê duyệt đổi ca
        $response = $this->actingAs($this->adminUser)->post("/rosters/swaps/{$swapRequest->id}/approve");

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_swap_requests', [
            'id' => $swapRequest->id,
            'status' => 'APPROVED',
        ]);

        // Kiểm tra ca làm việc đã được hoán đổi thành công
        $roster1->refresh();
        $roster2->refresh();

        $this->assertEquals($this->eveningShift->id, $roster1->shift_id, 'Employee 1 nhận ca chiều của Employee 2');
        $this->assertEquals($this->morningShift->id, $roster2->shift_id, 'Employee 2 nhận ca sáng của Employee 1');
    }
}
