<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Models\AttendancePolicyConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class TimesheetWorkMonthAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Màn hình Chấm công chế độ WorkMonth hiển thị ma trận tháng thành công
     */
    public function test_attendance_workmonth_matrix_renders_successfully(): void
    {
        $admin = User::where('role', UserRole::SUPER_ADMIN->value)->first();

        $response = $this->actingAs($admin)->get('/attendance?view=workmonth&month_year=2026-09');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Attendance/Index')
            ->where('view', 'workmonth')
            ->has('workMonthData.calendar_days')
            ->has('workMonthData.rows')
            ->has('lifecyclePhase')
            ->has('branches')
            ->has('departments')
        );
    }

    /**
     * Màn hình Chấm công chế độ WorkDate hiển thị điểm danh ngày thành công
     */
    public function test_attendance_workdate_overview_renders_successfully(): void
    {
        $admin = User::where('role', UserRole::SUPER_ADMIN->value)->first();

        $response = $this->actingAs($admin)->get('/attendance?view=workdate&work_date=2026-09-03');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Attendance/Index')
            ->where('view', 'workdate')
            ->has('workDateData.stats')
            ->has('workDateData.rows')
        );
    }

    /**
     * Nhân viên nộp bảng công tháng của mình
     */
    public function test_employee_can_submit_timesheet(): void
    {
        $emp = Employee::where('user_code', 'EMP001')->first();
        $user = User::where('employee_id', $emp->id)->first() ?: User::factory()->create([
            'tenant_id' => $emp->tenant_id,
            'employee_id' => $emp->id,
            'role' => UserRole::EMPLOYEE,
        ]);

        $response = $this->actingAs($user)->post('/attendance/submit', [
            'month_year' => '2026-09',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('monthly_attendances', [
            'tenant_id' => $emp->tenant_id,
            'employee_id' => $emp->id,
            'month_year' => '2026-09',
            'submission_status' => 'SUBMITTED',
            'submission_type' => 'MANUAL',
        ]);
    }

    /**
     * Quản lý phê duyệt và từ chối bảng công
     */
    public function test_manager_can_approve_and_reject_timesheet(): void
    {
        $admin = User::where('role', UserRole::HR_ADMIN->value)->first();
        $emp = Employee::where('user_code', 'EMP001')->first();

        $attendance = MonthlyAttendance::updateOrCreate(
            [
                'tenant_id' => $emp->tenant_id,
                'employee_id' => $emp->id,
                'month_year' => '2026-11',
            ],
            [
                'submission_status' => 'SUBMITTED',
                'submission_type' => 'MANUAL',
                'total_standard_days' => 22.0,
                'total_ot_hours' => 5.0,
                'total_late_minutes' => 0,
                'total_penalty_amount' => 0.0,
                'is_locked' => false,
            ]
        );

        // Duyệt bảng công
        $approveRes = $this->actingAs($admin)->post("/attendance/approve/{$attendance->id}");
        $approveRes->assertRedirect();

        $this->assertDatabaseHas('monthly_attendances', [
            'id' => $attendance->id,
            'submission_status' => 'HIGHER_MANAGER_APPROVED',
        ]);

        // Đổi trạng thái sang SUBMITTED rồi Từ chối kèm lý do
        $attendance->update(['submission_status' => 'SUBMITTED']);

        $rejectRes = $this->actingAs($admin)->post("/attendance/reject/{$attendance->id}", [
            'reason' => 'Thiếu giải trình ngày 05/09 đi muộn 30 phút.',
        ]);
        $rejectRes->assertRedirect();

        $this->assertDatabaseHas('monthly_attendances', [
            'id' => $attendance->id,
            'submission_status' => 'REJECTED',
            'rejection_reason' => 'Thiếu giải trình ngày 05/09 đi muộn 30 phút.',
        ]);
    }

    /**
     * Quản lý phê duyệt hàng loạt bảng công
     */
    public function test_manager_can_bulk_approve_timesheets(): void
    {
        $admin = User::where('role', UserRole::HR_ADMIN->value)->first();
        $employees = Employee::take(3)->get();

        $ids = [];
        foreach ($employees as $e) {
            $record = MonthlyAttendance::updateOrCreate(
                [
                    'tenant_id' => $e->tenant_id,
                    'employee_id' => $e->id,
                    'month_year' => '2026-11',
                ],
                [
                    'submission_status' => 'SUBMITTED',
                    'submission_type' => 'MANUAL',
                    'total_standard_days' => 20.0,
                    'total_ot_hours' => 0.0,
                    'total_late_minutes' => 0,
                    'total_penalty_amount' => 0.0,
                    'is_locked' => false,
                ]
            );
            $ids[] = $record->id;
        }

        $res = $this->actingAs($admin)->post('/attendance/bulk-approve', [
            'ids' => $ids,
        ]);
        $res->assertRedirect();

        foreach ($ids as $id) {
            $this->assertDatabaseHas('monthly_attendances', [
                'id' => $id,
                'submission_status' => 'HIGHER_MANAGER_APPROVED',
            ]);
        }
    }

    /**
     * Cập nhật cấu hình chu trình đóng công Zero-Hardcode qua API
     */
    public function test_update_attendance_policy_lifecycle_configs(): void
    {
        $admin = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $version = PolicyVersion::where('tenant_id', $admin->tenant_id)->where('is_active', true)->first();

        $response = $this->actingAs($admin)->put("/policies/attendance-config/{$version->id}", [
            'grace_period_late_minutes' => 7,
            'grace_period_early_minutes' => 0,
            'leave_unit_hours' => 4.0,
            'early_leave_unit_hours' => 4.0,
            'min_rest_hours_between_shifts' => 12,
            'min_weekly_rest_hours' => 24,
            'max_weekly_standard_hours' => 48,
            'ot_to_rostered_rate' => 1.5,
            'compensation_rate' => 1.0,
            'enable_auto_submit_timesheet' => true,
            'emp_review_deadline_day' => 3,
            'auto_submit_day' => 4,
            'manager_approval_deadline_day' => 5,
            'higher_manager_deadline_day' => 6,
            'hr_lock_deadline_day' => 7,
            'payroll_payment_day' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_policy_configs', [
            'policy_version_id' => $version->id,
            'grace_period_late_minutes' => 7,
            'emp_review_deadline_day' => 3,
            'auto_submit_day' => 4,
            'manager_approval_deadline_day' => 5,
            'hr_lock_deadline_day' => 7,
            'payroll_payment_day' => 10,
        ]);
    }

    /**
     * Lệnh Artisan attendance:auto-submit tự động submit công tháng
     */
    public function test_artisan_auto_submit_command(): void
    {
        $exitCode = Artisan::call('attendance:auto-submit', [
            '--force' => true,
            '--month' => '2026-08',
        ]);

        $this->assertEquals(0, $exitCode);

        // Kiểm tra có bản ghi monthly_attendances của tháng 2026-08 được AUTO_SUBMITTED
        $this->assertDatabaseHas('monthly_attendances', [
            'month_year' => '2026-08',
            'submission_status' => 'SUBMITTED',
            'submission_type' => 'AUTO_SUBMITTED',
        ]);
    }
}
