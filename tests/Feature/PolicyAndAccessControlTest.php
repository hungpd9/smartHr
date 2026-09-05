<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Models\PayrollPolicyConfig;
use App\Modules\PolicyEngine\Models\WelfareScheme;
use App\Modules\PolicyEngine\Models\Allowance;
use App\Modules\PolicyEngine\Models\ChildWelfarePolicy;
use App\Modules\CoreHR\Models\PermissionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyAndAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Chỉ Super Admin mới có quyền truy cập màn hình Ma trận Phân quyền (/access-control)
     */
    public function test_access_control_requires_super_admin(): void
    {
        $employee = User::where('role', UserRole::EMPLOYEE->value)->first();
        $superAdmin = User::where('role', UserRole::SUPER_ADMIN->value)->first();

        // 1. Employee bị 403
        $this->actingAs($employee)->get('/access-control')->assertStatus(403);

        // 2. Super Admin được 200
        $response = $this->actingAs($superAdmin)->get('/access-control');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('AccessControl/Index')
            ->has('assignments')
            ->has('modules')
            ->has('roles')
            ->has('departments')
            ->has('positions')
            ->has('employees')
        );
    }

    /**
     * Super Admin cập nhật Ma trận Phân quyền & Ủy quyền thành công
     */
    public function test_super_admin_can_update_permission_matrix(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMIN->value)->first();

        $payload = [
            'assignments' => [
                [
                    'subject_type' => 'ROLE',
                    'subject_id' => null,
                    'subject_role' => 'HR_ADMIN',
                    'module_code' => 'PAYROLL_SALARY',
                    'can_view' => true,
                    'can_manage' => false,
                    'can_approve' => false,
                    'notes' => 'Chỉ xem lương, không được sửa',
                ],
                [
                    'subject_type' => 'ROLE',
                    'subject_id' => null,
                    'subject_role' => 'ACCOUNTANT',
                    'module_code' => 'PAYROLL_SALARY',
                    'can_view' => true,
                    'can_manage' => true,
                    'can_approve' => true,
                    'notes' => 'Kế toán quản lý toàn bộ thu nhập',
                ],
                [
                    'subject_type' => 'DEPARTMENT',
                    'subject_id' => 1,
                    'subject_role' => null,
                    'module_code' => 'OFFICE_RESOURCES',
                    'can_view' => true,
                    'can_manage' => true,
                    'can_approve' => false,
                    'notes' => 'Phòng ban quản lý máy tính văn phòng',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post('/access-control/update', $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permission_assignments', [
            'subject_type' => 'ROLE',
            'subject_role' => 'HR_ADMIN',
            'module_code' => 'PAYROLL_SALARY',
            'can_view' => true,
            'can_manage' => false,
        ]);

        $this->assertDatabaseHas('permission_assignments', [
            'subject_type' => 'ROLE',
            'subject_role' => 'ACCOUNTANT',
            'module_code' => 'PAYROLL_SALARY',
            'can_view' => true,
            'can_manage' => true,
            'can_approve' => true,
        ]);
    }

    /**
     * Cập nhật Cấu hình Lương, Thuế TNCN và Bảo hiểm Mới trong Trung tâm Cấu hình
     */
    public function test_update_payroll_policy_config_successfully(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $activeVersion = PolicyVersion::where('is_active', true)->first();

        $payload = [
            'standard_working_days_mode' => 'FIXED',
            'fixed_standard_working_days' => 24.0,
            'standard_working_hours_per_day' => 8.0,
            'payment_day' => 10,
            'pay_early_on_holiday_weekend' => true,
            'personal_deduction' => 15400000,
            'dependent_deduction' => 6160000,
            'default_child_allowance' => 300000,
            'base_salary' => 2340000,
            'max_salary_bhxh' => 46800000,
            'max_salary_bhtn' => 100000000,
            'company_bhxh_rate' => 17.5,
            'company_bhyt_rate' => 3.0,
            'company_bhtn_rate' => 1.0,
            'employee_bhxh_rate' => 8.0,
            'employee_bhyt_rate' => 1.5,
            'employee_bhtn_rate' => 1.0,
            'probation_salary_percentage' => 85.0,
            'probation_pit_rate' => 10.0,
            'probation_pit_threshold' => 2000000,
            'ot_weekday_day_rate' => 1.5,
            'ot_weekday_night_rate' => 2.0,
            'ot_weekend_day_rate' => 2.0,
            'ot_weekend_night_rate' => 2.7,
            'ot_holiday_day_rate' => 3.0,
            'ot_holiday_night_rate' => 3.9,
            'ot_tax_exempt_daily_limit_percent' => 50,
            'ot_tax_exempt_monthly_limit_hours' => 40,
            'ot_tax_exempt_yearly_limit_hours' => 200,
        ];

        $response = $this->actingAs($superAdmin)->put("/policies/payroll-config/{$activeVersion->id}", $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_policy_configs', [
            'policy_version_id' => $activeVersion->id,
            'payment_day' => 10,
            'fixed_standard_working_days' => 24.0,
            'personal_deduction' => 15400000,
            'dependent_deduction' => 6160000,
        ]);
    }

    /**
     * Tạo chế độ Phúc lợi Doanh nghiệp và Chuyển trạng thái Hiện / Ẩn (VISIBLE / HIDDEN)
     */
    public function test_welfare_scheme_crud_and_visibility_toggle(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $activeVersion = PolicyVersion::where('is_active', true)->first();

        // 1. Tạo Chế độ Phúc lợi Doanh nghiệp
        $createPayload = [
            'code' => 'GYM_FITNESS',
            'name' => 'Hỗ trợ Thẻ tập Gym & Yoga',
            'category' => 'HEALTH_WELLNESS',
            'visibility' => 'VISIBLE',
            'is_statutory' => false,
            'daily_exemption_minutes' => 0,
            'pay_type' => 'PAID',
            'prohibit_night_shift' => false,
            'prohibit_overtime' => false,
            'monthly_allowance_amount' => 500000,
            'is_tax_exempt' => false,
            'applicable_gender' => 'ALL',
            'description' => 'Trợ cấp rèn luyện sức khỏe nhân sự',
        ];

        $response = $this->actingAs($superAdmin)->post('/policies/welfare-schemes', $createPayload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $scheme = WelfareScheme::where('code', 'GYM_FITNESS')->first();
        $this->assertNotNull($scheme);
        $this->assertEquals('VISIBLE', $scheme->visibility);

        // 2. Chuyển sang Ẩn (HIDDEN)
        $toggleResponse = $this->actingAs($superAdmin)->patch("/policies/welfare-schemes/{$scheme->id}/toggle");
        $toggleResponse->assertRedirect();

        $scheme->refresh();
        $this->assertEquals('HIDDEN', $scheme->visibility);

        // 3. Chuyển lại Hiện (VISIBLE)
        $toggleResponse2 = $this->actingAs($superAdmin)->patch("/policies/welfare-schemes/{$scheme->id}/toggle");
        $toggleResponse2->assertRedirect();

        $scheme->refresh();
        $this->assertEquals('VISIBLE', $scheme->visibility);
    }

    /**
     * Tạo Danh mục Phụ cấp Lương và Chuyển trạng thái Hiện / Ẩn (VISIBLE / HIDDEN)
     */
    public function test_allowance_crud_and_visibility_toggle(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $activeVersion = PolicyVersion::where('is_active', true)->first();

        // 1. Tạo Phụ cấp
        $createPayload = [
            'code' => 'PC_DIEN_THOAI',
            'name' => 'Phụ cấp Tiền điện thoại di động',
            'apply_type' => 'COMPANY_FIXED',
            'default_amount' => 400000,
            'is_insurance_taxable' => false,
            'is_pit_taxable' => false,
            'is_included_in_ot_wage' => false,
            'tax_exemption_limit' => 500000,
            'visibility' => 'VISIBLE',
            'notes' => 'Trợ cấp liên lạc công vụ hàng tháng',
        ];

        $response = $this->actingAs($superAdmin)->post('/policies/allowances', $createPayload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $allowance = Allowance::where('code', 'PC_DIEN_THOAI')->first();
        $this->assertNotNull($allowance);
        $this->assertEquals('VISIBLE', $allowance->visibility);

        // 2. Chuyển sang Ẩn (HIDDEN)
        $toggleResponse = $this->actingAs($superAdmin)->patch("/policies/allowances/{$allowance->id}/toggle");
        $toggleResponse->assertRedirect();

        $allowance->refresh();
        $this->assertEquals('HIDDEN', $allowance->visibility);
    }

    /**
     * Chuyển trạng thái Hiện / Ẩn chính sách Con nhỏ
     */
    public function test_child_welfare_policy_visibility_toggle(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $childPolicy = ChildWelfarePolicy::first();
        $this->assertNotNull($childPolicy);

        // Ban đầu là VISIBLE
        $this->assertEquals('VISIBLE', $childPolicy->visibility);

        // Gạt switch toggle sang HIDDEN
        $response = $this->actingAs($superAdmin)->patch("/policies/child-welfare/{$childPolicy->id}/toggle");
        $response->assertRedirect();

        $childPolicy->refresh();
        $this->assertEquals('HIDDEN', $childPolicy->visibility);
    }
}
