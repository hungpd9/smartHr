<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\Payroll\Services\EmployeeSalaryService;
use App\Modules\Payroll\Services\PayrollCalculationService;
use App\Modules\PolicyEngine\Models\PayrollPolicyConfig;
use App\Modules\PolicyEngine\Models\PitTaxBracket;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollEngineEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected PolicyVersion $policyVersion;
    protected PayrollPolicyConfig $payrollPolicy;
    protected Employee $employeeOfficial;
    protected Employee $employeeProbation;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo Tenant & Branch
        $this->tenant = Tenant::create([
            'name' => 'Công Ty Cổ Phần Công Nghệ Toàn Cầu',
            'code' => 'GLOBAL_TECH',
            'domain' => 'global-tech.smarthr.vn',
            'status' => 'ACTIVE',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_TECH',
            'name' => 'Chi nhánh Công nghệ Hà Nội',
            'secret_key' => 'global_tech_secret_key_123',
        ]);

        // 2. Tạo PolicyVersion & PayrollPolicyConfig (Zero-Hardcode Policy)
        $this->policyVersion = PolicyVersion::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'POL_GLOBAL_2026',
            'name' => 'Quy Chế Lao Động & Tiền Lương 2026',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->payrollPolicy = PayrollPolicyConfig::create([
            'policy_version_id' => $this->policyVersion->id,
            'fixed_standard_working_days' => 22.0,
            'employee_bhxh_rate' => 8.0,
            'employee_bhyt_rate' => 1.5,
            'employee_bhtn_rate' => 1.0,
            'company_bhxh_rate' => 17.5,
            'company_bhyt_rate' => 3.0,
            'company_bhtn_rate' => 1.0,
            'max_salary_bhxh' => 46800000.0,
            'max_salary_bhtn' => 99200000.0,
            'personal_deduction' => 11000000.0,
            'dependent_deduction' => 4400000.0,
            'probation_pit_threshold' => 2000000.0,
            'probation_pit_rate' => 10.0,
            'probation_salary_percentage' => 100.0,
            'ot_weekday_day_rate' => 1.5,
            'ot_weekday_night_rate' => 2.0,
            'ot_weekend_day_rate' => 2.0,
            'ot_holiday_day_rate' => 3.0,
        ]);

        // Tạo 5 bậc thuế TNCN lũy tiến động (Từ 01/2026)
        PitTaxBracket::create(['policy_version_id' => $this->policyVersion->id, 'tier' => 1, 'min_income' => 0, 'max_income' => 10000000, 'tax_rate' => 5.0, 'quick_subtraction' => 0]);
        PitTaxBracket::create(['policy_version_id' => $this->policyVersion->id, 'tier' => 2, 'min_income' => 10000001, 'max_income' => 30000000, 'tax_rate' => 15.0, 'quick_subtraction' => 1000000]);
        PitTaxBracket::create(['policy_version_id' => $this->policyVersion->id, 'tier' => 3, 'min_income' => 30000001, 'max_income' => 60000000, 'tax_rate' => 25.0, 'quick_subtraction' => 4000000]);
        PitTaxBracket::create(['policy_version_id' => $this->policyVersion->id, 'tier' => 4, 'min_income' => 60000001, 'max_income' => 100000000, 'tax_rate' => 30.0, 'quick_subtraction' => 7000000]);
        PitTaxBracket::create(['policy_version_id' => $this->policyVersion->id, 'tier' => 5, 'min_income' => 100000001, 'max_income' => null, 'tax_rate' => 35.0, 'quick_subtraction' => 12000000]);

        // 3. Tạo Nhân viên Chính thức và Thử việc
        $this->employeeOfficial = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_OFFICIAL_01',
            'full_name' => 'Trần Văn Chính',
            'gender' => 'MALE',
            'birth_date' => '1992-06-15',
            'email' => 'chinh.tran@globaltech.com',
            'join_date' => '2025-01-01',
            'status' => 'OFFICIAL',
        ]);

        $this->employeeProbation = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_PROBATION_02',
            'full_name' => 'Lê Thị Thử',
            'gender' => 'FEMALE',
            'birth_date' => '2001-08-20',
            'email' => 'thu.le@globaltech.com',
            'join_date' => '2026-08-01',
            'status' => 'PROBATION',
        ]);

        // 4. Tạo User Super Admin
        $this->superAdmin = User::create([
            'name' => 'Global Super Admin',
            'email' => 'superadmin@globaltech.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::SUPER_ADMIN->value,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test 1: Tính lương sử dụng đúng mức lương cá nhân từ employee_salaries theo Effective Dating
     */
    public function test_payroll_calculation_uses_employee_salaries_effective_dating(): void
    {
        // Thiết lập mức lương cũ: 15.000.000đ từ 2026-01-01
        EmployeeSalary::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 15000000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-01-01',
            'end_date' => '2026-08-31',
            'is_locked' => false,
        ]);

        // Thiết lập mức lương mới (tăng lương): 25.000.000đ từ 2026-09-01
        EmployeeSalary::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 25000000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-09-01',
            'end_date' => null,
            'is_locked' => false,
        ]);

        // Bảng công tháng 08/2026 (Đã duyệt)
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-08',
            'total_standard_days' => 22.0,
            'total_ot_hours' => 0.0,
            'is_approved' => true,
            'approved_at' => Carbon::now(),
        ]);

        // Bảng công tháng 09/2026 (Đã duyệt)
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'total_ot_hours' => 0.0,
            'is_approved' => true,
            'approved_at' => Carbon::now(),
        ]);

        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);

        // Tính lương tháng 08
        $resultAug = $payrollService->calculateForMonth($this->tenant->id, '2026-08');
        $this->assertTrue($resultAug['success']);
        $recordAug = PayrollRecord::where('employee_id', $this->employeeOfficial->id)->where('month_year', '2026-08')->first();
        $this->assertNotNull($recordAug);
        $this->assertEquals(15000000.0, (float)($recordAug->salary_breakdown['earnings']['contract_base_salary'] ?? 0), 'Lương cơ bản tháng 08 phải là 15M theo effective dating');

        // Tính lương tháng 09
        $resultSep = $payrollService->calculateForMonth($this->tenant->id, '2026-09');
        $this->assertTrue($resultSep['success']);
        $recordSep = PayrollRecord::where('employee_id', $this->employeeOfficial->id)->where('month_year', '2026-09')->first();
        $this->assertNotNull($recordSep);
        $this->assertEquals(25000000.0, (float)($recordSep->salary_breakdown['earnings']['contract_base_salary'] ?? 0), 'Lương cơ bản tháng 09 phải là 25M theo effective dating mới');
    }

    /**
     * Test 2: Tính toán bảo hiểm và chi phí người sử dụng lao động theo Zero-Hardcode Policy
     */
    public function test_payroll_insurance_and_employer_cost_calculated_from_policy_config(): void
    {
        // Mức lương đóng bảo hiểm 20.000.000đ
        EmployeeSalary::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 20000000.0,
            'insurance_salary' => 20000000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-09-01',
            'is_locked' => false,
        ]);

        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'is_approved' => true,
            'approved_at' => Carbon::now(),
        ]);

        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);
        $result = $payrollService->calculateForMonth($this->tenant->id, '2026-09');
        $this->assertTrue($result['success']);

        $record = PayrollRecord::where('employee_id', $this->employeeOfficial->id)->where('month_year', '2026-09')->first();
        $this->assertNotNull($record);

        // Kiểm tra trích nộp bảo hiểm người lao động:
        // BHXH 8% = 1.600.000, BHYT 1.5% = 300.000, BHTN 1.0% = 200.000 => Tổng 2.100.000
        $totalEmpInsurance = (float)$record->social_insurance_employee + (float)$record->health_insurance_employee + (float)$record->unemployment_insurance_employee;
        $this->assertEquals(2100000.0, $totalEmpInsurance);

        // Kiểm tra breakdown chi phí NSDLĐ:
        $breakdown = $record->salary_breakdown;
        $this->assertIsArray($breakdown);
        $employer = $breakdown['company_contributions'];
        $this->assertEquals(3500000.0, (float)$employer['bhxh'], 'NSDLĐ đóng BHXH 17.5% = 3.500.000');
        $this->assertEquals(600000.0, (float)$employer['bhyt'], 'NSDLĐ đóng BHYT 3.0% = 600.000');
        $this->assertEquals(200000.0, (float)$employer['bhtn'], 'NSDLĐ đóng BHTN 1.0% = 200.000');
        $this->assertEquals(4700000.0, (float)$employer['total'], 'Tổng chi phí bảo hiểm NSDLĐ (kèm 2% công đoàn) = 4.700.000');
    }

    /**
     * Test 3: Bóc tách thu nhập làm thêm giờ (OT) miễn thuế theo Nghị định 145/2020/NĐ-CP
     */
    public function test_overtime_income_tax_exemption_according_to_decree_145(): void
    {
        // Lương cơ bản 17.600.000đ (22 ngày tiêu chuẩn x 8h = 176h => 100.000đ/giờ)
        EmployeeSalary::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 17600000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-09-01',
            'is_locked' => false,
        ]);

        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'total_ot_hours' => 10.0,
            'is_approved' => true,
            'approved_at' => Carbon::now(),
        ]);

        // Tạo 1 ngày làm việc có 10h OT ngày thường (hệ số 1.5x)
        // Lương 1h thường = 100.000đ.
        // Làm thêm 10h: Tổng tiền OT = 10h x 100.000 x 1.5 = 1.500.000đ.
        // Trong đó:
        // - Chịu thuế = 10h x 100.000 = 1.000.000đ (mức tương đương giờ làm việc bình thường)
        // - Miễn thuế = 10h x 100.000 x 0.5 = 500.000đ (phần phụ trội trả cao hơn theo NĐ 145/2020)
        DailyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'work_date' => '2026-09-10',
            'actual_work_hours' => 8.0,
            'ot_day_hours' => 10.0,
            'ot_night_hours' => 0.0,
            'ot_weekend_hours' => 0.0,
            'ot_holiday_hours' => 0.0,
            'is_locked' => false,
        ]);

        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);
        $result = $payrollService->calculateForMonth($this->tenant->id, '2026-09');
        $this->assertTrue($result['success']);

        $record = PayrollRecord::where('employee_id', $this->employeeOfficial->id)->where('month_year', '2026-09')->first();
        $this->assertNotNull($record);

        $totalOt = (float)$record->tax_exempt_ot_amount + (float)$record->taxable_ot_amount;
        $this->assertEquals(1500000.0, $totalOt, 'Tổng tiền làm thêm giờ = 1.500.000đ');

        $this->assertEquals(1000000.0, (float)$record->taxable_ot_amount, 'Phần OT chịu thuế phải là 1.000.000đ');
        $this->assertEquals(500000.0, (float)$record->tax_exempt_ot_amount, 'Phần OT miễn thuế theo NĐ 145/2020 phải là 500.000đ');
    }

    /**
     * Test 4: Lao động thử việc (PROBATION) khấu trừ thuế toàn phần 10% (ngưỡng >= 2M), không đóng BHXH
     */
    public function test_probation_employee_applies_withholding_tax_10_percent_without_insurance(): void
    {
        // Thiết lập lương thử việc 10.000.000đ
        EmployeeSalary::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeProbation->id,
            'base_salary' => 10000000.0,
            'contract_type' => 'PROBATION',
            'effective_date' => '2026-09-01',
            'is_locked' => false,
        ]);

        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeProbation->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'is_approved' => true,
            'approved_at' => Carbon::now(),
        ]);

        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);
        $result = $payrollService->calculateForMonth($this->tenant->id, '2026-09');
        $this->assertTrue($result['success']);

        $record = PayrollRecord::where('employee_id', $this->employeeProbation->id)->where('month_year', '2026-09')->first();
        $this->assertNotNull($record);

        // Thử việc không đóng bảo hiểm
        $totalEmpInsurance = (float)$record->social_insurance_employee + (float)$record->health_insurance_employee + (float)$record->unemployment_insurance_employee;
        $this->assertEquals(0.0, $totalEmpInsurance, 'Thử việc không khấu trừ BHXH/BHYT/BHTN');

        // Thuế TNCN khấu trừ toàn phần 10% trên tổng thu nhập 10M = 1.000.000đ
        $this->assertEquals(1000000.0, (float)$record->pit_tax_amount, 'Thuế TNCN thử việc là 10% = 1.000.000đ');

        // Lương thực nhận (Net) = 10.000.000 - 1.000.000 = 9.000.000đ
        $this->assertEquals(9000000.0, (float)$record->net_salary);
    }

    /**
     * Test 5: Bảng công chưa duyệt tự động bị bỏ qua và xuất hiện trong báo cáo skipped
     */
    public function test_unapproved_monthly_attendance_is_skipped_and_reported(): void
    {
        // Cả 2 nhân viên đều chưa duyệt bảng công
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'is_approved' => false,
        ]);

        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);
        $result = $payrollService->calculateForMonth($this->tenant->id, '2026-09');

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['calculated_count']);
        $this->assertEquals(2, $result['skipped_count'], 'Cả 2 nhân viên chưa duyệt công đều bị bỏ qua');
        $this->assertCount(2, $result['skipped_employees']);

        $this->assertDatabaseMissing('payroll_records', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-09',
        ]);
    }

    /**
     * Test 6: Khóa sổ kỳ lương ngăn chặn tính toán lại và cấm sửa đổi hồi tố mức lương cá nhân
     */
    public function test_temporal_locking_prevents_salary_retroactive_modification_and_recalculation(): void
    {
        // 1. Tạo mức lương ban đầu
        EmployeeSalary::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 15000000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-09-01',
            'is_locked' => false,
        ]);

        // 2. Tạo kỳ lương đã khóa sổ
        PayrollPeriod::create([
            'tenant_id' => $this->tenant->id,
            'month_year' => '2026-09',
            'policy_version_id' => $this->policyVersion->id,
            'status' => 'APPROVED',
            'is_locked' => true,
            'locked_at' => Carbon::now(),
        ]);

        // 3. Cố gắng tính toán lại kỳ lương đã khóa -> Phải ném ngoại lệ
        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);
        $this->expectException(ValidationException::class);
        $payrollService->calculateForMonth($this->tenant->id, '2026-09');
    }

    /**
     * Test 6b: Kiểm tra sửa đổi mức lương trong khoảng thời gian đã khóa bị chặn
     */
    public function test_cannot_modify_salary_in_locked_payroll_period(): void
    {
        // Tạo kỳ lương 2026-09 đã khóa sổ
        PayrollPeriod::create([
            'tenant_id' => $this->tenant->id,
            'month_year' => '2026-09',
            'policy_version_id' => $this->policyVersion->id,
            'status' => 'APPROVED',
            'is_locked' => true,
            'locked_at' => Carbon::now(),
        ]);

        /** @var EmployeeSalaryService $salaryService */
        $salaryService = app(EmployeeSalaryService::class);

        // Cố gắng tạo mới mức lương rơi vào tháng 09/2026 -> Phải ném ValidationException
        $this->expectException(ValidationException::class);
        $salaryService->createSalary($this->tenant->id, [
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 18000000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-09-15',
        ]);
    }

    /**
     * Test 7: API phiếu lương trả về chi tiết breakdown minh bạch
     */
    public function test_payslip_api_returns_detailed_breakdown_successfully(): void
    {
        // Tạo bản ghi PayrollRecord kèm breakdown
        $record = PayrollRecord::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'month_year' => '2026-09',
            'gross_salary' => 24230000.0,
            'social_insurance_employee' => 1600000.0,
            'health_insurance_employee' => 300000.0,
            'unemployment_insurance_employee' => 200000.0,
            'pit_tax_amount' => 500000.0,
            'net_salary' => 21630000.0,
            'status' => 'DRAFT',
            'salary_breakdown' => [
                'earnings' => [
                    'contract_base_salary' => 20000000.0,
                    'total_ot_pay' => 1500000.0,
                ],
                'deductions' => [
                    'employee_insurance' => [
                        'total' => 2100000.0,
                    ],
                    'pit_tax' => 500000.0,
                ],
                'net_salary' => 21630000.0,
            ],
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson("/payroll/records/{$record->id}/payslip");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'record',
            'salary_breakdown',
        ]);
        $response->assertJson([
            'record' => [
                'id' => $record->id,
                'employee_id' => $this->employeeOfficial->id,
                'net_salary' => '21630000.00',
            ],
        ]);
    }

    /**
     * Test 8: Super Admin có thể thêm mức lương cá nhân qua API
     */
    public function test_super_admin_can_store_employee_salary_via_api(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/payroll/salaries', [
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 30000000.0,
            'insurance_salary' => 25000000.0,
            'contract_type' => 'OFFICIAL',
            'effective_date' => '2026-10-01',
            'notes' => 'Tăng lương quý 4/2026',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employee_salaries', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employeeOfficial->id,
            'base_salary' => 30000000.0,
            'contract_type' => 'OFFICIAL',
        ]);
    }
}
