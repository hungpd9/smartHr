<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeChild;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\LeavePolicy\Models\LeaveBalance;
use App\Modules\LeavePolicy\Models\LeaveTransaction;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\LeavePolicy\Services\LeaveBalanceService;
use App\Modules\LeavePolicy\Services\OvertimeComplianceService;
use App\Modules\LeavePolicy\Enums\LeaveType;
use App\Modules\LeavePolicy\Enums\LeaveTransactionType;
use App\Modules\LeavePolicy\Enums\SpecialLeaveType;
use App\Modules\PolicyEngine\Models\WelfareScheme;
use App\Modules\Compliance\Models\AuditTrail;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\Attendance\Models\DailyAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeavePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Màn hình Quản trị Quỹ Phép hiển thị thành công cho HR_ADMIN
     */
    public function test_leave_balances_index_renders_successfully(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();

        $response = $this->actingAs($user)->get('/leave/balances?year=2026');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Leave/Balances')
            ->has('balances')
            ->has('kpis')
            ->has('departments')
            ->has('employeesList')
            ->has('filters')
        );
    }

    /**
     * Kiểm tra Điều 114 BLLĐ 2019: Đủ 5 năm thâm niên tự động cộng 1 ngày phép
     */
    public function test_five_year_seniority_bonus_allocation(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $employee->join_date = Carbon::parse('2021-01-01');
        $employee->save();

        $service = app(LeaveBalanceService::class);
        $balance = $service->initializeOrUpdateAnnualBalance($employee, 2026, Carbon::parse('2026-01-01'));

        $this->assertEquals(12.00, (float) $balance->statutory_allocated_days);
        $this->assertEquals(1.00, (float) $balance->seniority_bonus_days, 'Đủ 5 năm thâm niên phải được cộng 1 ngày phép');
        $this->assertEquals(13.00, (float) $balance->total_entitled_days);
        $this->assertEquals(13.00, (float) $balance->remaining_days);

        // Kiểm tra lịch sử giao dịch ACCRUAL
        $this->assertDatabaseHas('leave_transactions', [
            'leave_balance_id' => $balance->id,
            'transaction_type' => LeaveTransactionType::ACCRUAL->value,
            'days' => 13.00,
        ]);
    }

    /**
     * Kiểm tra Điều 114 BLLĐ 2019: Đủ 10 năm thâm niên tự động cộng 2 ngày phép
     */
    public function test_ten_year_seniority_bonus_allocation(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $employee->join_date = Carbon::parse('2016-01-01');
        $employee->save();

        $service = app(LeaveBalanceService::class);
        $balance = $service->initializeOrUpdateAnnualBalance($employee, 2026, Carbon::parse('2026-01-01'));

        $this->assertEquals(12.00, (float) $balance->statutory_allocated_days);
        $this->assertEquals(2.00, (float) $balance->seniority_bonus_days, 'Đủ 10 năm thâm niên phải được cộng 2 ngày phép');
        $this->assertEquals(14.00, (float) $balance->total_entitled_days);
    }

    /**
     * Kiểm tra cộng ngày phép từ Chế độ Phúc lợi Doanh nghiệp (welfare_schemes)
     */
    public function test_welfare_scheme_additional_leave_granted(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $employee->join_date = Carbon::parse('2023-01-01');
        $employee->save();

        // Tạo chế độ phúc lợi cộng 2 ngày phép/năm
        WelfareScheme::create([
            'tenant_id' => $employee->tenant_id,
            'code' => 'SCHEME_SENIOR_BONUS',
            'name' => 'Chế độ Tri ân Cống hiến',
            'category' => 'SPECIAL_ALLOWANCE',
            'visibility' => 'VISIBLE',
            'is_statutory' => false,
            'additional_leave_days_per_year' => 2.0,
            'min_seniority_months' => 12,
            'applicable_gender' => 'ALL',
        ]);

        $service = app(LeaveBalanceService::class);
        $balance = $service->initializeOrUpdateAnnualBalance($employee, 2026, Carbon::parse('2026-01-01'));

        $this->assertEquals(2.00, (float) $balance->welfare_bonus_days);
        $this->assertEquals(14.00, (float) $balance->total_entitled_days);
    }

    /**
     * Kiểm tra bảo lưu phép tồn và tự động hết hạn sau 31/03 (Carry-over Expiration)
     */
    public function test_carry_over_expiration_after_march_31(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();

        // Giả lập năm trước có phép tồn 3.5 ngày
        $prevBalance = LeaveBalance::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'year' => 2025,
            'leave_type' => LeaveType::ANNUAL->value,
            'total_entitled_days' => 12.00,
            'used_days' => 8.50,
            'remaining_days' => 3.50,
        ]);

        $service = app(LeaveBalanceService::class);
        $balance2026 = $service->initializeOrUpdateAnnualBalance($employee, 2026, Carbon::parse('2026-01-01'));

        $this->assertEquals(3.50, (float) $balance2026->carried_over_days);
        $this->assertEquals('2026-03-31', $balance2026->carried_over_expiry_date->toDateString());
        $this->assertEquals(15.50, (float) $balance2026->remaining_days);

        // Chạy kiểm tra hết hạn vào ngày 01/04/2026
        $expiredCount = $service->expireCarriedOverBalances(2026, Carbon::parse('2026-04-01'));

        $this->assertEquals(1, $expiredCount);
        $balance2026->refresh();
        $this->assertEquals(0.00, (float) $balance2026->carried_over_days);
        $this->assertEquals(12.00, (float) $balance2026->remaining_days);

        // Ghi nhận transaction loại EXPIRATION
        $this->assertDatabaseHas('leave_transactions', [
            'leave_balance_id' => $balance2026->id,
            'transaction_type' => LeaveTransactionType::EXPIRATION->value,
            'days' => -3.50,
        ]);
    }

    /**
     * Kiểm tra quy đổi số giờ làm thêm (OT) sang ngày nghỉ bù (Comp-off)
     */
    public function test_convert_ot_hours_to_comp_off_days(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();
        $employee = Employee::where('user_code', 'EMP001')->first();

        $response = $this->actingAs($user)->post('/leave/convert-ot', [
            'employee_id' => $employee->id,
            'year' => 2026,
            'ot_hours' => 16.0,
            'conversion_rate' => 8.0,
            'description' => 'Quy đổi 16h OT thành 2 ngày nghỉ bù',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $compBalance = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', 2026)
            ->where('leave_type', LeaveType::COMPENSATION->value)
            ->first();

        $this->assertNotNull($compBalance);
        $this->assertEquals(2.00, (float) $compBalance->remaining_days);
        $this->assertEquals(16.00, (float) $compBalance->comp_off_hours);

        $this->assertDatabaseHas('leave_transactions', [
            'leave_balance_id' => $compBalance->id,
            'transaction_type' => LeaveTransactionType::COMPENSATION_CONVERT->value,
            'days' => 2.00,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'action_type' => 'CONVERT_OT_TO_COMPOFF',
            'auditable_type' => LeaveBalance::class,
            'auditable_id' => $compBalance->id,
        ]);
    }

    /**
     * Kiểm tra điều chỉnh thủ công (Manual Adjustment) có lưu vết kiểm toán
     */
    public function test_manual_balance_adjustment_creates_audit_trail(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();
        $employee = Employee::where('user_code', 'EMP001')->first();

        $service = app(LeaveBalanceService::class);
        $balance = $service->initializeOrUpdateAnnualBalance($employee, 2026);

        $response = $this->actingAs($user)->post('/leave/adjust', [
            'leave_balance_id' => $balance->id,
            'days' => 1.5,
            'transaction_type' => LeaveTransactionType::ADJUSTMENT->value,
            'description' => 'Cộng bù 1.5 ngày do thành tích xuất sắc',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $balance->refresh();
        $this->assertEquals(13.50, (float) $balance->remaining_days);

        $this->assertDatabaseHas('leave_transactions', [
            'leave_balance_id' => $balance->id,
            'transaction_type' => LeaveTransactionType::ADJUSTMENT->value,
            'days' => 1.50,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'action_type' => 'ADJUST_LEAVE',
            'auditable_type' => LeaveBalance::class,
            'auditable_id' => $balance->id,
        ]);
    }

    /**
     * Kiểm tra giám sát trần làm thêm giờ: Phát hiện vi phạm trần 40h/tháng và 200h/năm
     */
    public function test_overtime_compliance_caps_monitoring(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();

        // Giả lập dữ liệu chấm công có số giờ OT lớn trong năm 2026
        // Tạo 42h trong tháng 3 và tổng 222h trong năm
        for ($i = 10; $i <= 19; $i++) {
            DailyAttendance::updateOrCreate(
                [
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'work_date' => Carbon::create(2026, 3, $i)->toDateString(),
                ],
                [
                    'standard_hours' => 8.0,
                    'ot_day_hours' => 4.2, // 10 ngày * 4.2h = 42h (> 40h)
                    'status' => 'PRESENT',
                ]
            );
        }

        for ($m = 4; $m <= 12; $m++) {
            DailyAttendance::updateOrCreate(
                [
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'work_date' => Carbon::create(2026, $m, 15)->toDateString(),
                ],
                [
                    'standard_hours' => 8.0,
                    'ot_day_hours' => 20.0, // 9 tháng * 20h = 180h -> Tổng 222h (> 200h)
                    'status' => 'PRESENT',
                ]
            );
        }

        $service = app(OvertimeComplianceService::class);
        $accumulation = $service->syncEmployeeOvertime($employee, 2026);

        $this->assertTrue($accumulation->is_exceeded_200h);
        $this->assertGreaterThan(200.0, (float) $accumulation->total_ot_hours_year);
        $this->assertEquals(42.0, (float) ($accumulation->monthly_ot_json['03'] ?? 0));

        // Kiểm tra kích hoạt cảnh báo tuân thủ
        $this->assertDatabaseHas('compliance_alerts', [
            'employee_id' => $employee->id,
            'alert_type' => 'OT_ANNUAL_CAP_BREACH',
            'severity' => 'CRITICAL',
        ]);

        $this->assertDatabaseHas('compliance_alerts', [
            'employee_id' => $employee->id,
            'alert_type' => 'OT_BREACH_RED_40H',
            'severity' => 'CRITICAL',
        ]);
    }

    /**
     * Kiểm tra kiểm soát giờ OT trong ngày và bảo vệ thai sản/con nhỏ theo Điều 137
     */
    public function test_daily_ot_limits_and_article_137_restrictions(): void
    {
        $service = app(OvertimeComplianceService::class);
        $employee = Employee::where('user_code', 'EMP001')->first();

        // 1. Kiểm tra trần 50% ngày làm việc (Đề xuất 5h trên ca 8h -> Vi phạm)
        $check1 = $service->checkDailyOtLimit($employee, Carbon::today(), 5.0, 8.0);
        $this->assertFalse($check1['allowed']);
        $this->assertStringContainsString('Vượt trần 50%', $check1['reason']);

        // 2. Đề xuất 3h trên ca 8h -> Đạt
        $check2 = $service->checkDailyOtLimit($employee, Carbon::today(), 3.0, 8.0);
        $this->assertTrue($check2['allowed']);

        // 3. Lao động nữ nuôi con dưới 12 tháng (Điều 137 Khoản 1 Điểm b)
        $femaleEmp = Employee::create([
            'tenant_id' => $employee->tenant_id,
            'user_code' => 'FEMALE01',
            'full_name' => 'Nguyễn Thị Hoa',
            'gender' => 'FEMALE',
            'email' => 'hoa.nguyen@smarthr.vn',
            'phone' => '0912345678',
            'citizen_id' => '012345678901',
            'birth_date' => Carbon::parse('1998-05-15'),
            'join_date' => Carbon::parse('2024-01-01'),
            'status' => 'OFFICIAL',
        ]);

        EmployeeChild::create([
            'tenant_id' => $femaleEmp->tenant_id,
            'employee_id' => $femaleEmp->id,
            'full_name' => 'Bé Nguyễn An',
            'birth_date' => Carbon::today()->subMonths(6), // 6 tháng tuổi (< 12 tháng)
            'is_active' => true,
        ]);

        $checkChild = $service->checkDailyOtLimit($femaleEmp, Carbon::today(), 2.0, 8.0);
        $this->assertFalse($checkChild['allowed']);
        $this->assertTrue($checkChild['requires_voluntary_consent']);
        $this->assertStringContainsString('nuôi con dưới 12 tháng', $checkChild['reason']);
    }

    /**
     * Màn hình Giám sát Trần Giờ Làm Thêm hiển thị thành công cho HR_ADMIN
     */
    public function test_overtime_accumulations_screen_renders_successfully(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();

        $response = $this->actingAs($user)->get('/leave/ot-accumulations?year=2026');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Leave/OvertimeAccumulations')
            ->has('accumulations')
            ->has('summary')
            ->has('departments')
            ->has('filters')
        );
    }
}
