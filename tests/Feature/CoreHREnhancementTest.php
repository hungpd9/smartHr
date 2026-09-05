<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeChild;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Models\User;
use Carbon\Carbon;

class CoreHREnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $hrAdmin = User::where('role', \App\Enums\UserRole::HR_ADMIN)->first();
        $this->actingAs($hrAdmin);
    }

    public function test_employee_children_crud_and_counts(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $this->assertNotNull($employee);

        // 1. Xem trang Quản lý con nhỏ
        $response = $this->get('/employee-children');
        $response->assertStatus(200);

        // 2. Thêm con nhỏ mới
        $postData = [
            'employee_id' => $employee->id,
            'full_name' => 'Nguyễn Minh Khang',
            'gender' => 'MALE',
            'birth_date' => Carbon::now()->subMonths(7)->format('Y-m-d'),
            'birth_certificate_number' => 'GKS-2026-TEST',
            'is_tax_dependent' => true,
            'tax_dependent_code' => 'MST_KHANG_01',
            'has_monthly_allowance' => true,
            'monthly_allowance_amount' => 300000,
            'notes' => 'Con thứ 2 của anh An',
        ];

        $storeResponse = $this->post('/employee-children', $postData);
        $storeResponse->assertRedirect();

        $child = EmployeeChild::where('birth_certificate_number', 'GKS-2026-TEST')->first();
        $this->assertNotNull($child);
        $this->assertEquals(7, $child->ageInMonthsAt(Carbon::now()));

        // Kiểm tra bộ đếm trên Employee được cập nhật tự động
        $employee->refresh();
        $this->assertEquals(2, $employee->children_count);
        $this->assertEquals(2, $employee->tax_dependents_count);

        // 3. Cập nhật thông tin con nhỏ
        $updateResponse = $this->put("/employee-children/{$child->id}", array_merge($postData, [
            'full_name' => 'Nguyễn Minh Khang (Updated)',
            'monthly_allowance_amount' => 500000,
            'is_active' => true,
        ]));
        $updateResponse->assertRedirect();
        $this->assertEquals('Nguyễn Minh Khang (Updated)', $child->fresh()->full_name);

        // 4. Xóa con nhỏ
        $deleteResponse = $this->delete("/employee-children/{$child->id}");
        $deleteResponse->assertRedirect();
        $this->assertNull(EmployeeChild::find($child->id));

        $employee->refresh();
        $this->assertEquals(1, $employee->children_count);
    }

    public function test_employee_update_and_offboarding(): void
    {
        $employee = Employee::where('user_code', 'EMP004')->first();
        $this->assertNotNull($employee);

        // 1. Cập nhật thông tin hồ sơ
        $updateData = [
            'full_name' => 'Đặng Quốc Hưng (Cập nhật)',
            'gender' => 'MALE',
            'birth_date' => '1990-11-08',
            'email' => 'hung.dang.update@smarthr.vn',
            'phone' => '0905999888',
            'attendance_code' => '1004',
            'citizen_id' => '048090002345',
            'hometown' => 'Đà Nẵng City',
            'permanent_address' => 'Hải Châu, Đà Nẵng',
            'temporary_address' => 'Sơn Trà, Đà Nẵng',
            'join_date' => '2024-03-01',
            'probation_end_date' => '2024-05-01',
            'status' => 'OFFICIAL',
            'is_pregnant' => false,
        ];

        $updateResponse = $this->put("/core-hr/employees/{$employee->id}", $updateData);
        $updateResponse->assertRedirect();

        $employee->refresh();
        $this->assertEquals('Đặng Quốc Hưng (Cập nhật)', $employee->full_name);
        $this->assertEquals('hung.dang.update@smarthr.vn', $employee->email);

        // 2. Quy trình Thôi việc (Offboarding)
        $offboardData = [
            'resigned_date' => '2026-09-15',
            'resignation_reason' => 'Chuyển công tác về TP.HCM',
            'decision_number' => 'QĐ-TV-2026-04',
            'decision_date' => '2026-09-01',
            'notes' => 'Bàn giao đầy đủ dự án Cloud',
        ];

        $offboardResponse = $this->post("/core-hr/employees/{$employee->id}/offboard", $offboardData);
        $offboardResponse->assertRedirect();

        $employee->refresh();
        $this->assertEquals('TERMINATED', $employee->status);
        $this->assertEquals('2026-09-15', $employee->resigned_date->format('Y-m-d'));
        $this->assertEquals('Chuyển công tác về TP.HCM', $employee->resignation_reason);

        // Kiểm tra Timeline mốc OFFBOARDING được tự động tạo
        $offboardTimeline = $employee->careerTimelines()->where('event_type', 'OFFBOARDING')->first();
        $this->assertNotNull($offboardTimeline);
        $this->assertEquals('2026-09-15', $offboardTimeline->effective_date->format('Y-m-d'));
        $this->assertStringContainsString('Thâm niên', $offboardTimeline->notes);
    }
}
