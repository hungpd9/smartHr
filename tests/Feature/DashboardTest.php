<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Carbon\Carbon;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Branch;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $user = \App\Models\User::where('role', \App\Enums\UserRole::HR_ADMIN)->first();
        $this->actingAs($user);
    }

    public function test_dashboard_renders_successfully_with_inertia(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('metrics.totalEmployees')
            ->has('metrics.todayPunchesCount')
            ->has('metrics.pendingRequestsCount')
            ->has('metrics.activeComplianceAlertsCount')
            ->has('todayAttendances')
            ->has('careerEvents')
            ->has('policyVersion')
            ->has('analytics.burnoutData')
            ->has('analytics.turnoverData')
            ->has('analytics.headcountData')
            ->has('analytics.healthIndex')
        );
    }

    public function test_child_under_12_months_statutory_logic_article_137(): void
    {
        $femaleEmp = Employee::where('user_code', 'EMP002')->first();
        $this->assertNotNull($femaleEmp, 'EMP002 must exist in seeded database');

        $now = Carbon::now();
        $this->assertTrue(
            $femaleEmp->hasChildUnder12MonthsAt($now),
            'EMP002 has 8-month-old child, must qualify for Article 137.1.b protection'
        );

        $futureDate = Carbon::now()->addMonths(5); // Khi con 13 tháng tuổi
        $this->assertFalse(
            $femaleEmp->hasChildUnder12MonthsAt($futureDate),
            'When child reaches 13 months, Article 137.1.b restriction terminates'
        );
    }

    public function test_pregnant_in_remote_island_branch_threshold_logic(): void
    {
        $remoteBranch = Branch::where('code', 'PQ_STORE')->first();
        $normalBranch = Branch::where('code', 'HN_HQ')->first();
        $this->assertNotNull($remoteBranch);
        $this->assertNotNull($normalBranch);

        $pregnantEmp = Employee::where('user_code', 'EMP003')->first();
        $this->assertNotNull($pregnantEmp, 'EMP003 must exist in seeded database');

        $now = Carbon::now();
        // Tại chi nhánh Phú Quốc (vùng đảo), mang thai 6 tháng đã kích hoạt cấm ca đêm/OT
        $this->assertTrue(
            $pregnantEmp->isPregnantNightRestrictedAt($remoteBranch, $now),
            'At remote/island branch, 6-month pregnancy triggers night/OT ban (Article 137.1.a)'
        );

        // Tại chi nhánh Hà Nội (vùng thường), ngưỡng là 7 tháng
        $this->assertFalse(
            $pregnantEmp->isPregnantNightRestrictedAt($normalBranch, $now),
            'At normal branch, 6-month pregnancy does NOT trigger night/OT ban yet until month 7'
        );
    }
}
