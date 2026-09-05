<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use App\Modules\CoreHR\Services\PermissionResolutionService;
use App\Modules\CoreHR\Models\PermissionAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PerformanceAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * 1. Kiểm tra Tầng Caching trong PolicyResolutionService: Tránh lặp query DB & Xóa cache chính xác
     */
    public function test_policy_resolution_caching_and_invalidation(): void
    {
        $service = app(PolicyResolutionService::class);
        $tenantId = 1;
        $date = Carbon::create(2026, 9, 1);
        $cacheKey = "smart_hr:policy_version:{$tenantId}:" . $date->format('Y-m-d');

        // Đảm bảo cache sạch trước khi test
        Cache::forget($cacheKey);

        // Lần 1: Gọi resolveForDate -> Lưu vào Cache
        $version1 = $service->resolveForDate($tenantId, $date);
        $this->assertNotNull($version1);
        $this->assertTrue(Cache::has($cacheKey));

        // Lần 2: Lấy trực tiếp từ Cache mà không query lại PolicyVersion
        DB::enableQueryLog();
        $version2 = $service->resolveForDate($tenantId, $date);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals($version1->id, $version2->id);
        $this->assertEquals(0, $queryCount, 'Lần 2 phải đọc từ Cache, không được phát sinh DB query.');

        // Kiểm tra Invalidation: Gọi clearPolicyCache -> Cache biến mất
        $service->clearPolicyCache($tenantId);
        $this->assertFalse(Cache::has($cacheKey), 'Sau khi clearPolicyCache, key cache của ngày đó phải bị xóa.');
    }

    /**
     * 2. Kiểm tra Cache Analytics Dashboard: Response nhanh, không query nặng liên tục
     */
    public function test_dashboard_analytics_caching(): void
    {
        $user = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $cacheKey = "smart_hr:dashboard_analytics:{$tenantId}";

        Cache::forget($cacheKey);

        // Lần 1: Gọi dashboard -> Cache được khởi tạo
        $response1 = $this->actingAs($user)->get('/dashboard');
        $response1->assertStatus(200);
        $this->assertTrue(Cache::has($cacheKey));

        // Lần 2: Lấy từ Cache
        $response2 = $this->actingAs($user)->get('/dashboard');
        $response2->assertStatus(200);
    }

    /**
     * 3. Kiểm tra Bảo mật Middleware Phân quyền Ma trận (RBAC Matrix Middleware): Chặn 403 chuẩn xác
     */
    public function test_matrix_permission_middleware_enforcement(): void
    {
        $employee = User::where('role', UserRole::EMPLOYEE->value)->first();
        $hrAdmin = User::where('role', UserRole::HR_ADMIN->value)->first();
        $activeVersion = PolicyVersion::where('is_active', true)->first();

        // 3.1 Employee không có quyền CORE_HR -> Bị chặn 403
        $this->actingAs($employee)->get('/core-hr')->assertStatus(403);

        // 3.2 HR Admin có quyền CORE_HR -> Vào được 200
        $this->actingAs($hrAdmin)->get('/core-hr')->assertStatus(200);

        // 3.3 HR Admin mặc định không có quyền PAYROLL_SALARY,manage -> Bị chặn 403 khi sửa cấu hình lương
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

        // Đảm bảo không có bản ghi gán quyền riêng cho HR_ADMIN lúc này
        PermissionAssignment::where('subject_role', 'HR_ADMIN')
            ->where('module_code', 'PAYROLL_SALARY')
            ->delete();

        $this->actingAs($hrAdmin)->put("/policies/payroll-config/{$activeVersion->id}", $payload)->assertStatus(403);

        // 3.4 Bây giờ cấp quyền PAYROLL_SALARY,can_manage cho HR_ADMIN qua bảng PermissionAssignment
        PermissionAssignment::create([
            'tenant_id' => 1,
            'subject_type' => 'ROLE',
            'subject_role' => 'HR_ADMIN',
            'module_code' => 'PAYROLL_SALARY',
            'can_view' => true,
            'can_manage' => true,
            'can_approve' => false,
        ]);

        // Thử lại -> Bây giờ HR Admin được phép sửa lương thành công!
        $this->actingAs($hrAdmin)->put("/policies/payroll-config/{$activeVersion->id}", $payload)->assertRedirect();
    }
}
