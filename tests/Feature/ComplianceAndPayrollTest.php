<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Services\HelpGuideService;
use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Services\DepartmentService;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\Payroll\Services\PayrollCalculationService;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ComplianceAndPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected \App\Modules\CoreHR\Models\Branch $branch;
    protected Employee $employee1;
    protected Employee $employee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Công Ty Thử Nghiệm Smart-HR',
            'code' => 'TEST_CORP',
            'domain' => 'test-corp.smarthr.vn',
            'status' => 'ACTIVE',
        ]);

        $this->branch = \App\Modules\CoreHR\Models\Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_HQ',
            'name' => 'Trụ sở Hà Nội',
            'secret_key' => 'branch_secret_key_test_123456',
        ]);

        $this->employee1 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP001',
            'full_name' => 'Nguyễn Văn A',
            'gender' => 'MALE',
            'birth_date' => '1990-01-01',
            'email' => 'vana@test.com',
            'join_date' => '2025-01-01',
            'status' => 'OFFICIAL',
        ]);

        $this->employee2 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP002',
            'full_name' => 'Trần Thị B',
            'gender' => 'FEMALE',
            'birth_date' => '1995-05-05',
            'email' => 'thib@test.com',
            'join_date' => '2025-03-01',
            'status' => 'OFFICIAL',
        ]);
    }

    /**
     * Test quy tắc cốt lõi: Nhân viên chưa duyệt bảng chấm công -> Bỏ qua không tính lương
     */
    public function test_unapproved_timesheet_is_skipped_during_payroll_calculation(): void
    {
        $monthYear = '2026-09';

        // Employee 1: Có bảng chấm công ĐÃ PHÊ DUYỆT (is_approved = true)
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'month_year' => $monthYear,
            'total_standard_days' => 22.0,
            'total_ot_hours' => 5.0,
            'is_approved' => true,
            'approved_at' => Carbon::now(),
        ]);

        // Employee 2: Có bảng chấm công CHƯA PHÊ DUYỆT (is_approved = false)
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'month_year' => $monthYear,
            'total_standard_days' => 20.0,
            'total_ot_hours' => 2.0,
            'is_approved' => false,
        ]);

        /** @var PayrollCalculationService $payrollService */
        $payrollService = app(PayrollCalculationService::class);
        $result = $payrollService->calculateForMonth($this->tenant->id, $monthYear);

        // Kiểm tra kết quả
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['calculated_count'], 'Chỉ có đúng 1 nhân viên được duyệt công được tính lương');
        $this->assertEquals(1, $result['skipped_count'], 'Đúng 1 nhân viên chưa duyệt công bị bỏ qua');

        // Bản ghi PayrollRecord chỉ tồn tại cho Employee 1
        $this->assertDatabaseHas('payroll_records', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'month_year' => $monthYear,
        ]);

        $this->assertDatabaseMissing('payroll_records', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'month_year' => $monthYear,
        ]);
    }

    /**
     * Test khóa quy chế: Khi PolicyVersion bị khóa -> ném ngoại lệ khi cố gắng sửa
     */
    public function test_locked_policy_version_cannot_be_modified(): void
    {
        $policy = PolicyVersion::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'POL_2026_Q1',
            'name' => 'Quy Chế Lao Động Q1/2026',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        // Tạo một kỳ lương đã khóa liên kết với policy version này để mô phỏng quy chế đã áp dụng vào kỳ đã chốt
        \App\Modules\Payroll\Models\PayrollPeriod::create([
            'tenant_id' => $this->tenant->id,
            'month_year' => '2026-01',
            'policy_version_id' => $policy->id,
            'status' => 'APPROVED',
            'is_locked' => true,
            'locked_at' => Carbon::now(),
        ]);

        /** @var TemporalLockService $lockService */
        $lockService = app(TemporalLockService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $lockService->assertPolicyNotLocked($policy);
    }

    /**
     * Test bảo vệ toàn vẹn phòng ban: Không thể xóa phòng ban khi còn nhân viên
     */
    public function test_department_cannot_be_deleted_if_it_has_active_employees(): void
    {
        $department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'code' => 'TECH_DEPT',
            'name' => 'Phòng Công Nghệ',
            'level' => 1,
            'is_active' => true,
        ]);

        $position = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $department->id,
            'code' => 'SENIOR_DEV',
            'name' => 'Kỹ sư Cấp cao',
            'level' => 3,
        ]);

        // Gắn timeline cho Employee 1 vào phòng ban này
        \App\Modules\CoreHR\Models\EmployeeCareerTimeline::create([
            'employee_id' => $this->employee1->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'job_title' => 'Senior Developer',
            'effective_date' => '2025-01-01',
            'end_date' => null,
        ]);

        /** @var DepartmentService $deptService */
        $deptService = app(DepartmentService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $deptService->deleteDepartment($department, $this->employee1->id);
    }

    /**
     * Test phân quyền xem Help Guide theo vai trò (Employee không xem được hướng dẫn Super Admin)
     */
    public function test_employee_cannot_access_super_admin_help_guides(): void
    {
        /** @var HelpGuideService $helpService */
        $helpService = app(HelpGuideService::class);

        $canAccess = $helpService->canAccessRoleGuide('EMPLOYEE', 'super_admin');
        $this->assertFalse($canAccess, 'Nhân viên thông thường không có quyền đọc hướng dẫn Super Admin');

        $canAccessSelf = $helpService->canAccessRoleGuide('EMPLOYEE', 'employee');
        $this->assertTrue($canAccessSelf, 'Nhân viên có quyền đọc hướng dẫn Employee');
    }
}
