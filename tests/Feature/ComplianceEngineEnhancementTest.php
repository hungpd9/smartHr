<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\Compliance\Services\ComplianceScannerService;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\DepartmentVersion;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\CoreHR\Models\EmployeeContract;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ComplianceEngineEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected Department $department;
    protected Position $position;
    protected Employee $employee1;
    protected Employee $employeePregnant;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo Tenant & Branch
        $this->tenant = Tenant::create([
            'name' => 'Công Ty Cổ Phần Công Nghệ Sao Vàng',
            'code' => 'GOLD_STAR',
            'domain' => 'gold-star.smarthr.vn',
            'status' => 'ACTIVE',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_HQ',
            'name' => 'Trụ sở Hà Nội',
            'secret_key' => 'gold_star_secret_key_123',
            'is_remote_or_island' => false,
        ]);

        // 2. Tạo Phòng ban & Chức danh
        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'code' => 'DEPT_OPERATIONS',
            'name' => 'Phòng Vận Hành',
            'level' => 1,
            'is_active' => true,
        ]);

        DepartmentVersion::create([
            'department_id' => $this->department->id,
            'name' => 'Phòng Vận Hành Hệ Thống',
            'effective_date' => '2025-01-01',
        ]);

        $this->position = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'code' => 'OPS_STAFF',
            'name' => 'Chuyên viên Vận hành',
            'level' => 2,
        ]);

        // 3. Tạo Nhân viên thông thường
        $this->employee1 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_GOLD_01',
            'full_name' => 'Nguyễn Văn Tuấn',
            'gender' => 'MALE',
            'birth_date' => '1990-05-10',
            'email' => 'tuan.nguyen@goldstar.com',
            'join_date' => '2025-01-01',
            'status' => 'OFFICIAL',
        ]);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->employee1->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Chuyên viên Vận hành',
            'effective_date' => '2025-01-01',
        ]);

        // 4. Tạo Nhân viên Nữ mang thai tháng thứ 7
        $this->employeePregnant = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_GOLD_02',
            'full_name' => 'Phạm Thị Thúy',
            'gender' => 'FEMALE',
            'birth_date' => '1995-09-20',
            'email' => 'thuy.pham@goldstar.com',
            'join_date' => '2025-03-01',
            'status' => 'OFFICIAL',
            'is_pregnant' => true,
            'pregnancy_start_date' => Carbon::today()->subWeeks(30)->toDateString(), // 30 tuần ~ tháng thứ 7
            'pregnancy_weeks_at_start' => 0,
        ]);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->employeePregnant->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Chuyên viên Vận hành Nữ',
            'effective_date' => '2025-03-01',
        ]);

        // 5. Tạo Super Admin
        $this->superAdmin = User::create([
            'name' => 'Gold Star Admin',
            'email' => 'admin@goldstar.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::SUPER_ADMIN->value,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test 1: Quét phát hiện cảnh báo vàng cận trần làm thêm tháng (>= 35h)
     */
    public function test_scan_detects_monthly_overtime_yellow_warning_at_35_hours(): void
    {
        $today = Carbon::parse('2026-09-15');

        // Tạo tích lũy OT tháng = 36h (< 40h)
        OvertimeAccumulation::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'year' => 2026,
            'monthly_ot_json' => ['2026-09' => 36.0],
            'total_ot_hours_year' => 70.0,
        ]);

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanOvertimeCaps($this->tenant->id, $today);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'OT_WARNING_YELLOW_35H')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('WARNING', $alert->severity);
        $this->assertStringContainsString('36', $alert->description);
    }

    /**
     * Test 2: Quét phát hiện vi phạm nghiêm trọng trần làm thêm tháng 40 giờ (>= 40h)
     */
    public function test_scan_detects_monthly_overtime_red_breach_at_40_hours(): void
    {
        $today = Carbon::parse('2026-09-20');

        // Tạo tích lũy OT tháng = 42.5h (>= 40h)
        OvertimeAccumulation::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'year' => 2026,
            'monthly_ot_json' => ['2026-09' => 42.5],
            'total_ot_hours_year' => 120.0,
        ]);

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanOvertimeCaps($this->tenant->id, $today);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'OT_BREACH_RED_40H')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('CRITICAL', $alert->severity);
        $this->assertStringContainsString('Điều 107 Khoản 2 Điểm b BLLĐ 2019', $alert->description);
        $this->assertStringContainsString('42.5', $alert->description);
    }

    /**
     * Test 3: Quét phát hiện vi phạm trần làm thêm giờ năm 200 giờ (>= 200h)
     */
    public function test_scan_detects_annual_overtime_cap_breach(): void
    {
        $today = Carbon::parse('2026-11-10');

        OvertimeAccumulation::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'year' => 2026,
            'monthly_ot_json' => ['2026-11' => 25.0],
            'total_ot_hours_year' => 208.0,
        ]);

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanOvertimeCaps($this->tenant->id, $today);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'OT_ANNUAL_CAP_BREACH')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('CRITICAL', $alert->severity);
        $this->assertStringContainsString('208', $alert->description);
    }

    /**
     * Test 4: Quét phát hiện vi phạm khoảng cách nghỉ giữa 2 ca liên tiếp dưới 12 giờ
     */
    public function test_scan_detects_rest_between_shifts_under_12_hours(): void
    {
        $targetDate = Carbon::parse('2026-09-02');
        $prevDate = Carbon::parse('2026-09-01');

        // Ca tối: 14:00 - 22:00
        $shiftEvening = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SHIFT_EVE',
            'name' => 'Ca Chiều Tối',
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
            'break_minutes' => 0,
        ]);

        // Ca sáng sớm hôm sau: 06:00 - 14:00 (Khoảng cách từ 22:00 đến 06:00 = 8h < 12h)
        $shiftMorning = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SHIFT_MORN',
            'name' => 'Ca Sáng Sớm',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'break_minutes' => 0,
        ]);

        Roster::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $shiftEvening->id,
            'roster_date' => $prevDate->toDateString(),
            'is_published' => true,
        ]);

        Roster::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $shiftMorning->id,
            'roster_date' => $targetDate->toDateString(),
            'is_published' => true,
        ]);

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanRestIntervals($this->tenant->id, $targetDate);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'REST_BETWEEN_SHIFTS_BREACH')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('CRITICAL', $alert->severity);
        $this->assertStringContainsString('8 giờ', $alert->description);
        $this->assertStringContainsString('Điều 110 BLLĐ 2019', $alert->description);
    }

    /**
     * Test 5: Quét phát hiện vi phạm làm việc 7 ngày liên tục không nghỉ 24h
     */
    public function test_scan_detects_weekly_rest_breach(): void
    {
        $targetDate = Carbon::parse('2026-09-07'); // Ngày thứ 7 trong chuỗi

        // Tạo 7 ngày làm việc liên tiếp từ 01/09 đến 07/09
        for ($i = 0; $i < 7; $i++) {
            $workDate = Carbon::parse('2026-09-01')->addDays($i)->toDateString();
            DailyAttendance::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $this->employee1->id,
                'work_date' => $workDate,
                'standard_hours' => 8.0,
            ]);
        }

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanWeeklyRest($this->tenant->id, $targetDate);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'WEEKLY_REST_BREACH')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('WARNING', $alert->severity);
        $this->assertStringContainsString('7 ngày liên tiếp', $alert->description);
    }

    /**
     * Test 6: Quét phát hiện vi phạm bảo vệ thai sản làm việc ban đêm / làm thêm giờ
     */
    public function test_scan_detects_pregnant_employee_night_shift_violation(): void
    {
        $targetDate = Carbon::parse('2026-09-05');

        // Ca đêm: 22:00 - 06:00
        $shiftNight = Shift::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SHIFT_NIGHT',
            'name' => 'Ca Đêm',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_minutes' => 0,
            'is_night_shift' => true,
        ]);

        Roster::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeePregnant->id,
            'shift_id' => $shiftNight->id,
            'roster_date' => $targetDate->toDateString(),
            'is_published' => true,
        ]);

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanMaternityProtections($this->tenant->id, $targetDate);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employeePregnant->id)
            ->where('alert_type', 'PREGNANT_NIGHT_BREACH')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('CRITICAL', $alert->severity);
        $this->assertStringContainsString('Điều 137 Khoản 1 Điểm a BLLĐ 2019', $alert->description);
        $this->assertStringContainsString('Phạm Thị Thúy', $alert->title);
    }

    /**
     * Test 7: Quét phát hiện hợp đồng lao động sắp hết hạn trong vòng 30 ngày
     */
    public function test_scan_detects_contract_expiring_within_30_days(): void
    {
        $targetDate = Carbon::parse('2026-09-01');

        // Tạo hợp đồng hết hạn vào ngày 20/09/2026 (còn 19 ngày <= 30 ngày)
        EmployeeContract::create([
            'employee_id' => $this->employee1->id,
            'contract_number' => 'HDLD-2025-001',
            'contract_type' => 'DEFINITE_TERM',
            'sign_date' => '2025-09-20',
            'start_date' => '2025-09-20',
            'end_date' => '2026-09-20',
            'status' => 'ACTIVE',
        ]);

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanExpiringContracts($this->tenant->id, $targetDate);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'CONTRACT_EXPIRING_30D')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('WARNING', $alert->severity);
        $this->assertStringContainsString('19 ngày', $alert->title);
        $this->assertStringContainsString('HDLD-2025-001', $alert->description);
    }

    /**
     * Test 8: Quét phát hiện nguy cơ kiệt sức do làm việc cường độ cao (Burnout Risk)
     */
    public function test_scan_detects_burnout_risk_high(): void
    {
        $targetDate = Carbon::parse('2026-09-07');

        // Tạo 6 ngày làm việc có OT cao (mỗi ngày 3h OT -> tổng 18h OT tuần > 15h)
        for ($i = 0; $i < 6; $i++) {
            $workDate = Carbon::parse('2026-09-02')->addDays($i)->toDateString();
            DailyAttendance::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $this->employee1->id,
                'work_date' => $workDate,
                'standard_hours' => 8.0,
                'ot_day_hours' => 3.0,
            ]);
        }

        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);
        $scanner->scanBurnoutRisks($this->tenant->id, $targetDate);

        $alert = ComplianceAlert::where('tenant_id', $this->tenant->id)
            ->where('employee_id', $this->employee1->id)
            ->where('alert_type', 'BURNOUT_RISK_HIGH')
            ->first();

        $this->assertNotNull($alert);
        $this->assertEquals('WARNING', $alert->severity);
        $this->assertStringContainsString('Burnout', $alert->title);
    }

    /**
     * Test 9: Khắc phục cảnh báo (Resolve) cập nhật trạng thái và ghi nhận vào Audit Trail
     */
    public function test_resolve_alert_records_resolver_and_audit_trail(): void
    {
        $alert = ComplianceAlert::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'alert_type' => 'REST_BETWEEN_SHIFTS_BREACH',
            'severity' => 'CRITICAL',
            'title' => 'Nghỉ giữa 2 ca dưới 12h',
            'description' => 'Mô tả vi phạm',
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)->post("/compliance/alerts/{$alert->id}/resolve", [
            'resolution_notes' => 'Đã hoán đổi ca cho nhân sự sang ca sáng ngày 03/09',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $alert->refresh();
        $this->assertTrue($alert->is_resolved);
        $this->assertNotNull($alert->resolved_at);
        $this->assertStringContainsString('Đã hoán đổi ca cho nhân sự', $alert->description);

        // Kiểm tra Audit Trail ghi nhận sự kiện RESOLVE_COMPLIANCE_ALERT
        $this->assertDatabaseHas('audit_trails', [
            'tenant_id' => $this->tenant->id,
            'action_type' => 'RESOLVE_COMPLIANCE_ALERT',
            'auditable_type' => ComplianceAlert::class,
            'auditable_id' => $alert->id,
        ]);
    }

    /**
     * Test 10: Khắc phục hàng loạt nhiều cảnh báo
     */
    public function test_bulk_resolve_alerts_successfully(): void
    {
        $alert1 = ComplianceAlert::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'alert_type' => 'OT_WARNING_YELLOW_35H',
            'severity' => 'WARNING',
            'title' => 'Cảnh báo OT 35h',
            'description' => 'Mô tả 1',
            'is_resolved' => false,
        ]);

        $alert2 = ComplianceAlert::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeePregnant->id,
            'alert_type' => 'WEEKLY_REST_BREACH',
            'severity' => 'WARNING',
            'title' => 'Nghỉ 7 ngày liên tục',
            'description' => 'Mô tả 2',
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($this->superAdmin)->post('/compliance/alerts/bulk-resolve', [
            'alert_ids' => [$alert1->id, $alert2->id],
            'resolution_notes' => 'Đã bố trí ngày nghỉ bù cho các nhân sự',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertTrue($alert1->fresh()->is_resolved);
        $this->assertTrue($alert2->fresh()->is_resolved);
    }

    /**
     * Test 11: Lệnh Artisan compliance:scan chạy thành công
     */
    public function test_artisan_command_scans_compliance_successfully(): void
    {
        $exitCode = Artisan::call('compliance:scan', [
            '--tenant' => $this->tenant->id,
            '--date' => '2026-09-10',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test 12: Màn hình Compliance/Index render thành công qua Inertia
     */
    public function test_compliance_index_screen_renders_successfully(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Compliance/Index')
            ->has('alerts.data')
            ->has('stats.compliance_score')
            ->has('stats.critical_count')
            ->has('stats.warning_count')
            ->has('departments')
            ->has('branches')
            ->has('legalReferences')
            ->has('filters')
        );
    }
}
