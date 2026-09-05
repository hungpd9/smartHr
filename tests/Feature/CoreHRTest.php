<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Carbon\Carbon;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\CoreHR\Models\DepartmentVersion;

class CoreHRTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $user = \App\Models\User::where('role', \App\Enums\UserRole::HR_ADMIN)->first();
        $this->actingAs($user);
    }

    public function test_core_hr_index_renders_successfully(): void
    {
        $response = $this->get('/core-hr');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CoreHR/Index')
            ->has('employees.data')
            ->has('departments')
            ->has('positions')
            ->has('branches')
            ->has('filters')
        );
    }

    public function test_can_store_new_employee_and_auto_create_onboarding_timeline(): void
    {
        $dept = Department::first();
        $pos = Position::first();

        $payload = [
            'user_code' => 'EMP999',
            'full_name' => 'Nguyễn Văn Test',
            'gender' => 'MALE',
            'birth_date' => '1995-05-15',
            'email' => 'emp999@example.com',
            'phone' => '0988777666',
            'join_date' => '2026-03-01',
            'status' => 'OFFICIAL',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'is_pregnant' => false,
        ];

        $response = $this->post('/core-hr/employees', $payload);

        $response->assertRedirect('/core-hr');

        $employee = Employee::where('user_code', 'EMP999')->first();
        $this->assertNotNull($employee);
        $this->assertEquals('Nguyễn Văn Test', $employee->full_name);

        // Kiểm tra tự động tạo mốc sự nghiệp ONBOARDING
        $timeline = EmployeeCareerTimeline::where('employee_id', $employee->id)->first();
        $this->assertNotNull($timeline);
        $this->assertEquals('ONBOARDING', $timeline->event_type);
        $this->assertEquals($dept->id, $timeline->department_id);
        $this->assertEquals($pos->id, $timeline->position_id);
        $this->assertEquals('2026-03-01', $timeline->effective_date->format('Y-m-d'));
        $this->assertNull($timeline->end_date, 'Mốc sự nghiệp hiện tại phải có end_date = null');
    }

    public function test_core_hr_show_renders_with_career_timelines(): void
    {
        $employee = Employee::first();

        $response = $this->get("/core-hr/employees/{$employee->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CoreHR/Show')
            ->has('employee.id')
            ->has('employee.user_code')
            ->has('timelines')
            ->has('contracts')
            ->has('departments')
            ->has('positions')
            ->has('managers')
        );
    }

    public function test_add_career_event_performs_effective_dating(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $oldTimeline = $employee->currentTimeline;
        $this->assertNotNull($oldTimeline);
        $this->assertNull($oldTimeline->end_date);

        $newDept = Department::where('id', '!=', $oldTimeline->department_id)->first() ?? $oldTimeline->department;
        $newPos = Position::where('id', '!=', $oldTimeline->position_id)->first() ?? $oldTimeline->position;

        $effectiveDate = Carbon::now()->addDays(1)->format('Y-m-d');

        $payload = [
            'event_type' => 'PROMOTION',
            'department_id' => $newDept->id,
            'position_id' => $newPos->id,
            'job_title' => 'Chuyên viên Cao cấp',
            'effective_date' => $effectiveDate,
            'decision_number' => 'QĐ-2026/09-HR',
            'decision_date' => Carbon::now()->format('Y-m-d'),
            'notes' => 'Quyết định thăng chức và điều chuyển',
        ];

        $response = $this->post("/core-hr/employees/{$employee->id}/timeline", $payload);
        $response->assertSessionHas('success');

        // Kiểm tra bản ghi cũ đã được chốt mốc kết thúc (end_date = effective_date - 1 day)
        $oldTimeline->refresh();
        $expectedEndDate = Carbon::parse($effectiveDate)->subDay()->format('Y-m-d');
        $this->assertEquals($expectedEndDate, $oldTimeline->end_date?->format('Y-m-d'));

        // Kiểm tra bản ghi mới đã được tạo và đang active
        $newTimeline = EmployeeCareerTimeline::where('employee_id', $employee->id)
            ->where('event_type', 'PROMOTION')
            ->first();

        $this->assertNotNull($newTimeline);
        $this->assertNull($newTimeline->end_date);
        $this->assertEquals('Chuyên viên Cao cấp', $newTimeline->job_title);
    }

    public function test_update_department_version_effective_dating(): void
    {
        $dept = Department::first();
        $oldVersion = $dept->currentVersion;
        $this->assertNotNull($oldVersion);
        $this->assertNull($oldVersion->end_date);

        $effectiveDate = Carbon::now()->addDays(5)->format('Y-m-d');

        $payload = [
            'name' => 'Phòng Công nghệ & Chuyển đổi số',
            'effective_date' => $effectiveDate,
            'functions' => 'Phụ trách toàn bộ hạ tầng IT và phần mềm',
        ];

        $response = $this->post("/core-hr/departments/{$dept->id}/version", $payload);
        $response->assertSessionHas('success');

        $oldVersion->refresh();
        $expectedEndDate = Carbon::parse($effectiveDate)->subDay()->format('Y-m-d');
        $this->assertEquals($expectedEndDate, $oldVersion->end_date?->format('Y-m-d'));

        $newVersion = DepartmentVersion::where('department_id', $dept->id)
            ->where('name', 'Phòng Công nghệ & Chuyển đổi số')
            ->first();

        $this->assertNotNull($newVersion);
        $this->assertNull($newVersion->end_date);
    }
}
