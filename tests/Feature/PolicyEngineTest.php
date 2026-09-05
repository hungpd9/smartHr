<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    /**
     * Màn hình chính Policy Engine hiển thị thành công cho HR_ADMIN
     */
    public function test_policy_index_renders_successfully_for_hr_admin(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();

        $response = $this->actingAs($user)->get('/policies');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Policy/Index')
            ->has('activeVersion')
            ->has('versions')
            ->has('holidays')
            ->where('canEdit', true)
        );
    }

    /**
     * Nhân viên thông thường (EMPLOYEE) có quyền xem quy chế nhưng không có quyền sửa (canEdit = false)
     */
    public function test_employee_can_view_policies_as_readonly(): void
    {
        $user = User::where('role', UserRole::EMPLOYEE->value)->first();

        $response = $this->actingAs($user)->get('/policies');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Policy/Index')
            ->where('canEdit', false)
            ->where('canTest', true)
        );
    }

    /**
     * Kiểm tra tính thuế TNCN 5 bậc năm 2026 chính xác
     */
    public function test_pit_tax_calculation_5_tiers_2026(): void
    {
        $service = app(PolicyResolutionService::class);
        $tenantId = 1;
        $date = Carbon::create(2026, 3, 1);

        // Nấc 1: 8 triệu -> Thuế 5% = 400.000 đ
        $res1 = $service->calculatePitTax($tenantId, $date, 8000000);
        $this->assertEquals(1, $res1['tier']);
        $this->assertEquals(400000.0, $res1['tax_amount']);

        // Nấc 2: 20 triệu -> (20 tr * 10%) - 500k = 1.500.000 đ
        $res2 = $service->calculatePitTax($tenantId, $date, 20000000);
        $this->assertEquals(2, $res2['tier']);
        $this->assertEquals(1500000.0, $res2['tax_amount']);

        // Nấc 3: 50 triệu -> (50 tr * 20%) - 3.500.000 đ = 6.500.000 đ
        $res3 = $service->calculatePitTax($tenantId, $date, 50000000);
        $this->assertEquals(3, $res3['tier']);
        $this->assertEquals(6500000.0, $res3['tax_amount']);
    }

    /**
     * Kiểm tra tính phạt đi muộn với biên độ miễn trừ (grace period) và lũy tiến tần suất
     */
    public function test_late_penalty_with_grace_period_and_frequency(): void
    {
        $service = app(PolicyResolutionService::class);
        $tenantId = 1;
        $date = Carbon::create(2026, 3, 1);

        // 1. Đi muộn 4 phút (trong grace period 5 phút) -> Miễn phạt (0đ)
        $resGrace = $service->calculateLatePenalty($tenantId, $date, 4, 1);
        $this->assertTrue($resGrace['is_exempted']);
        $this->assertEquals(0.0, $resGrace['final_fine']);

        // 2. Đi muộn 10 phút, lần 1 (nấc 1-15p = 50.000 đ, hệ số x1.0) -> 50.000 đ
        $res1 = $service->calculateLatePenalty($tenantId, $date, 10, 1);
        $this->assertFalse($res1['is_exempted']);
        $this->assertEquals(50000.0, $res1['base_fine']);
        $this->assertEquals(1.0, $res1['multiplier']);
        $this->assertEquals(50000.0, $res1['final_fine']);

        // 3. Đi muộn 25 phút, lần 3 (nấc 16-30p = 100.000 đ, hệ số x1.5) -> 150.000 đ
        $res2 = $service->calculateLatePenalty($tenantId, $date, 25, 3);
        $this->assertEquals(100000.0, $res2['base_fine']);
        $this->assertEquals(1.5, $res2['multiplier']);
        $this->assertEquals(150000.0, $res2['final_fine']);
    }

    /**
     * Kiểm tra API Sandbox test calculation hoạt động chính xác
     */
    public function test_api_sandbox_calculation(): void
    {
        $user = User::where('role', UserRole::EMPLOYEE->value)->first();

        // Test phạt đi muộn
        $response = $this->actingAs($user)->postJson('/policies/test-calculation', [
            'type' => 'LATE_PENALTY',
            'late_minutes' => 20,
            'violation_count' => 5,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertEquals(2.0, $response->json('result.multiplier'));

        // Test thuế TNCN
        $responseTax = $this->actingAs($user)->postJson('/policies/test-calculation', [
            'type' => 'PIT_TAX',
            'taxable_income' => 30000000,
        ]);

        $responseTax->assertStatus(200);
        $responseTax->assertJsonPath('success', true);
        $responseTax->assertJsonPath('result.tier', 2);
    }
}
