<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use App\Models\User;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeChild;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Services\RosterService;
use App\Modules\Rostering\Solvers\AutoRosteringSolverService;
use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use App\Modules\Attendance\Pipelines\Pipes\ShiftMatchingPipe;

class DefaultShiftAndSmartRosteringTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected \App\Modules\CoreHR\Models\Branch $branch;
    protected User $user;
    protected Department $department;
    protected \App\Modules\CoreHR\Models\Position $position;
    protected Shift $dayShift;
    protected Shift $nightShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Công ty Cổ phần Công nghệ Smart-HR',
            'code' => 'SMART_HR',
            'domain' => 'smarthr.test',
            'is_active' => true,
        ]);

        $this->branch = \App\Modules\CoreHR\Models\Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_BRANCH',
            'name' => 'Chi nhánh Hà Nội',
            'secret_key' => 'secret_hn_123',
            'is_remote_or_island' => false,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'SUPER_ADMIN',
        ]);

        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'code' => 'IT_DEPT',
        ]);

        \App\Modules\CoreHR\Models\DepartmentVersion::create([
            'department_id' => $this->department->id,
            'name' => 'Phòng Công Nghệ Thông Tin',
            'effective_date' => '2025-01-01',
        ]);

        $this->position = \App\Modules\CoreHR\Models\Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'code' => 'DEV_POS',
            'name' => 'Lập trình viên',
            'level' => 1,
            'is_active' => true,
        ]);

        // Ca hành chính ban ngày (mặc định)
        $this->dayShift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HC_STANDARD',
            'name' => 'Ca Hành Chính Chuẩn',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_hours' => 8.0,
            'is_night_shift' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Ca đêm (22h - 6h)
        $this->nightShift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CA_DEM',
            'name' => 'Ca Đêm Vận Hành',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_minutes' => 60,
            'work_hours' => 7.0,
            'is_night_shift' => true,
            'is_default' => false,
            'night_allowance_rate' => 30.00,
            'night_bonus_amount' => 30000.00,
            'is_active' => true,
        ]);
    }

    protected function createTestEmployee(string $userCode, string $name, string $gender = 'MALE', string $status = 'OFFICIAL'): Employee
    {
        return Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => $userCode,
            'full_name' => $name,
            'gender' => $gender,
            'email' => strtolower($userCode) . '@smarthr.test',
            'birth_date' => '1995-05-15',
            'join_date' => '2024-01-01',
            'status' => $status,
        ]);
    }

    public function test_can_set_default_shift_and_unsets_previous_default(): void
    {
        $newShift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CA_CHIEU',
            'name' => 'Ca Chiều',
            'start_time' => '13:00:00',
            'end_time' => '21:00:00',
            'break_minutes' => 45,
            'work_hours' => 7.25,
            'is_night_shift' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/rosters/shifts/{$newShift->id}/set-default");

        $response->assertRedirect();
        
        $this->assertTrue($newShift->fresh()->is_default);
        $this->assertFalse($this->dayShift->fresh()->is_default);
    }

    public function test_resolve_default_shift_prioritizes_department_then_tenant(): void
    {
        $emp = $this->createTestEmployee('NV001', 'Nguyễn Văn A', 'MALE', 'OFFICIAL');

        EmployeeCareerTimeline::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $emp->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Kỹ sư phần mềm',
            'event_type' => 'ONBOARDING',
            'effective_date' => '2026-01-01',
        ]);

        /** @var RosterService $rosterService */
        $rosterService = app(RosterService::class);

        // 1. Khi phòng ban chưa có ca riêng -> lấy ca mặc định tenant (dayShift)
        $resolved = $rosterService->resolveDefaultShift($this->tenant->id, $emp->id, Carbon::parse('2026-09-01'));
        $this->assertNotNull($resolved);
        $this->assertEquals($this->dayShift->id, $resolved->id);

        // 2. Khi phòng ban cấu hình ca riêng
        $this->department->update(['default_shift_id' => $this->nightShift->id]);
        $resolvedDept = $rosterService->resolveDefaultShift($this->tenant->id, $emp->id, Carbon::parse('2026-09-01'));
        $this->assertNotNull($resolvedDept);
        $this->assertEquals($this->nightShift->id, $resolvedDept->id);
    }

    public function test_roster_matrix_automatically_inherits_default_shift_for_unscheduled_days(): void
    {
        $emp = $this->createTestEmployee('NV002', 'Trần Thị B', 'FEMALE', 'OFFICIAL');

        EmployeeCareerTimeline::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $emp->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Kỹ sư phần mềm',
            'event_type' => 'ONBOARDING',
            'effective_date' => '2026-01-01',
        ]);

        /** @var RosterService $rosterService */
        $rosterService = app(RosterService::class);

        // Lấy tuần từ Thứ 2 (2026-09-07) đến Chủ Nhật (2026-09-13)
        $data = $rosterService->getRosterMatrix($this->tenant->id, '2026-09-07', '2026-09-13');

        $this->assertNotEmpty($data['matrix']);
        $empRow = collect($data['matrix'])->firstWhere('employee_id', $emp->id);
        $this->assertNotNull($empRow);

        // Thứ 2 (2026-09-07) là ngày thường chưa xếp ca riêng -> tự động kế thừa ca mặc định
        $mondayCell = $empRow['days']['2026-09-07'];
        $this->assertTrue($mondayCell['is_default_inherited']);
        $this->assertEquals($this->dayShift->id, $mondayCell['shift_id']);
        $this->assertEquals('DEFAULT', $mondayCell['status']);

        // Chủ nhật (2026-09-13) là ngày cuối tuần -> không kế thừa ca hành chính (Nghỉ tuần)
        $sundayCell = $empRow['days']['2026-09-13'];
        $this->assertFalse($sundayCell['is_default_inherited']);
        $this->assertNull($sundayCell['shift_id']);
        $this->assertEquals('OFF', $sundayCell['status']);
    }

    public function test_shift_matching_pipe_resolves_default_shift_when_no_roster_exists(): void
    {
        $emp = $this->createTestEmployee('NV003', 'Lê Văn C', 'MALE', 'OFFICIAL');

        $pipe = app(ShiftMatchingPipe::class);

        // Ngày thường Thứ 3 (2026-09-08), không có Roster
        $context = new AttendanceCalculationContext(
            tenantId: $this->tenant->id,
            employeeId: $emp->id,
            workDate: Carbon::parse('2026-09-08')
        );

        $pipe->handle($context, function ($ctx) {
            return $ctx;
        });

        $this->assertNotNull($context->shift);
        $this->assertEquals($this->dayShift->id, $context->shift->id);
    }

    public function test_simulate_auto_generate_returns_breakdown_and_estimated_night_allowance_without_persisting(): void
    {
        $empMale = $this->createTestEmployee('NV_MALE', 'Hoàng Văn Nam', 'MALE', 'OFFICIAL');
        $empFemale = $this->createTestEmployee('NV_FEMALE', 'Phạm Thị Nữ', 'FEMALE', 'OFFICIAL');
        $empProbation = $this->createTestEmployee('NV_PROBATION', 'Đỗ Văn Thử Việc', 'MALE', 'PROBATION');

        $response = $this->actingAs($this->user)
            ->postJson('/rosters/simulate-auto-generate', [
                'start_date' => '2026-09-07',
                'end_date' => '2026-09-09',
                'strategy' => 'COST_OPTIMIZATION',
                'shift_ids' => [$this->dayShift->id, $this->nightShift->id],
                'employee_ids' => [$empMale->id, $empFemale->id, $empProbation->id],
                'preferences' => [
                    'avoid_female_night' => true,
                    'prioritize_low_base_salary' => true,
                ],
                'simulate_only' => true,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'data' => [
                'total_shifts_scheduled',
                'total_night_shifts',
                'total_rest_days',
                'employees_count',
                'violations_prevented',
                'estimated_night_allowance',
                'employee_breakdown',
                'roster_preview',
                'is_simulated',
            ],
        ]);

        $this->assertTrue($response->json('data.is_simulated'));

        // Không có bản ghi nào bị ghi vào CSDL trong chế độ mô phỏng
        $this->assertEquals(0, Roster::where('tenant_id', $this->tenant->id)->count());

        // Kiểm tra đối soát: nhân viên nữ không bị xếp ca đêm khi avoid_female_night = true
        $breakdown = collect($response->json('data.employee_breakdown'));
        $femaleStats = $breakdown->firstWhere('employee_id', $empFemale->id);
        $this->assertNotNull($femaleStats);
        $this->assertEquals(0, $femaleStats['night_shifts_count']);
    }
}
