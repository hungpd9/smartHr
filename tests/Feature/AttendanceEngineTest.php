<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Attendance\Services\AttendancePipelineService;
use App\Modules\Attendance\Services\TimesheetPreviewService;
use App\Modules\Attendance\Services\MonthlyAttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Màn hình Bảng Chấm công hiển thị thành công cho HR_ADMIN
     */
    public function test_attendance_index_renders_successfully(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();

        $response = $this->actingAs($user)->get('/attendance?month_year=2026-09');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Attendance/Index')
            ->has('days')
            ->has('summary')
            ->has('employees')
            ->where('canCalculate', true)
        );
    }

    /**
     * Kiểm tra Pipe 1 & Pipe 5: Quẹt đúng giờ -> Đủ 8.0h công chuẩn, 0p muộn, 0đ phạt
     */
    public function test_on_time_attendance_gives_full_standard_hours(): void
    {
        $emp1 = \App\Modules\CoreHR\Models\Employee::where('user_code', 'EMP001')->first();

        $record = DailyAttendance::where('employee_id', $emp1->id)
            ->whereDate('work_date', '2026-09-03')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(8.00, (float) $record->standard_hours);
        $this->assertEquals(0, $record->late_minutes);
        $this->assertEquals(0.00, (float) $record->late_penalty_amount);
        $this->assertEquals('PRESENT', $record->status);
    }

    /**
     * Kiểm tra Pipe 4: Lao động nuôi con 8 tháng tuổi được hấp thụ trọn vẹn 60 phút vi phạm (Điều 137 BLLĐ)
     */
    public function test_child_under_12m_welfare_exemption_absorbs_violation(): void
    {
        $emp2 = \App\Modules\CoreHR\Models\Employee::where('user_code', 'EMP002')->first();

        // Ngày 03/09: Mai quẹt muộn 45p, về sớm 15p -> Tổng 60p -> Hấp thụ hết 60p
        $record = DailyAttendance::where('employee_id', $emp2->id)
            ->whereDate('work_date', '2026-09-03')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(0, $record->late_minutes);
        $this->assertEquals(0, $record->early_minutes);
        $this->assertEquals(0.00, (float) $record->late_penalty_amount);
        $this->assertEquals('PRESENT', $record->status);
    }

    /**
     * Kiểm tra Pipe 6: Nhân viên đi muộn 22 phút bị phạt theo nấc 16-30p (100.000 VNĐ)
     */
    public function test_late_penalty_applied_correctly(): void
    {
        $emp1 = \App\Modules\CoreHR\Models\Employee::where('user_code', 'EMP001')->first();

        // Ngày 04/09: An quẹt 08:22 (muộn 22 phút)
        $record = DailyAttendance::where('employee_id', $emp1->id)
            ->whereDate('work_date', '2026-09-04')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(22, $record->late_minutes);
        $this->assertEquals(100000.00, (float) $record->late_penalty_amount);
        $this->assertEquals('LATE_EARLY', $record->status);
    }

    /**
     * Kiểm tra Pipe 7: Làm thêm giờ cuối tuần (Thứ Bảy 4h) tính vào ot_weekend_hours
     */
    public function test_weekend_overtime_calculation(): void
    {
        $emp1 = \App\Modules\CoreHR\Models\Employee::where('user_code', 'EMP001')->first();

        // Ngày 05/09/2026 là Thứ Bảy: Quẹt 08:00 - 12:00 -> 4.0h OT cuối tuần
        $record = DailyAttendance::where('employee_id', $emp1->id)
            ->whereDate('work_date', '2026-09-05')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(4.00, (float) $record->ot_weekend_hours);
    }

    /**
     * Kiểm tra Bảng công Tạm tính (Timesheet Preview): Tự động tích hợp đơn PENDING
     */
    public function test_timesheet_preview_applies_pending_requests(): void
    {
        $previewService = app(TimesheetPreviewService::class);
        $emp1 = \App\Modules\CoreHR\Models\Employee::where('user_code', 'EMP001')->first();

        $preview = $previewService->getPreviewTimesheet(1, (int) $emp1->id, '2026-09');

        // Tìm ngày 04/09 (có đơn sửa công PENDING từ 08:22 về 08:00)
        $day4 = collect($preview['days'])->firstWhere('work_date', '2026-09-04');

        $this->assertNotNull($day4);
        $this->assertTrue($day4['is_preview_modified']);
        $this->assertEquals('TIMESHEET_EDIT', $day4['pending_request_type']);
        $this->assertEquals(0, $day4['late_minutes']); // Đã được sửa thành 08:00 trong preview
        $this->assertEquals(0.00, (float) $day4['late_penalty_amount']);
    }

    /**
     * Kiểm tra Khóa kỳ công tháng: Khóa tháng ngăn chặn việc tính toán lại
     */
    public function test_monthly_lock_and_unlock(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();

        // 1. Khóa tháng
        $responseLock = $this->actingAs($user)->post('/attendance/lock-month', [
            'month_year' => '2026-09',
            'lock' => true,
        ]);
        $responseLock->assertSessionHas('success');

        $this->assertTrue(app(MonthlyAttendanceService::class)->isMonthLocked(1, '2026-09'));

        // 2. Thử tính lại công khi đã khóa -> Phải bị chặn
        $responseCalc = $this->actingAs($user)->post('/attendance/calculate', [
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-05',
        ]);
        $responseCalc->assertSessionHas('error');

        // 3. Mở khóa tháng
        $responseUnlock = $this->actingAs($user)->post('/attendance/lock-month', [
            'month_year' => '2026-09',
            'lock' => false,
        ]);
        $responseUnlock->assertSessionHas('success');
        $this->assertFalse(app(MonthlyAttendanceService::class)->isMonthLocked(1, '2026-09'));
    }
}
