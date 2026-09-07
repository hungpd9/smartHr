<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeChild;
use App\Modules\LeavePolicy\Models\LeaveBalance;
use App\Modules\LeavePolicy\Models\LeaveTransaction;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\LeavePolicy\Enums\LeaveType;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalHistory;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Enums\RequestType;
use App\Modules\Workflow\Enums\RequestStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Màn hình Quản lý Luồng Đơn từ hiển thị thành công qua Inertia
     */
    public function test_workflow_index_renders_successfully(): void
    {
        $user = User::where('role', UserRole::HR_ADMIN->value)->first();

        $response = $this->actingAs($user)->get('/workflow/requests');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Workflow/Index')
            ->has('requestsToApprove')
            ->has('myRequests')
            ->has('workflowConfigs')
            ->has('requestTypes')
        );
    }

    /**
     * Nộp đơn xin nghỉ phép năm: Tự động tạm giữ số ngày phép và đóng băng chuỗi người duyệt snapshot
     */
    public function test_submitting_leave_request_holds_pending_days_and_snapshots_approvers(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $user = User::where('email', $employee->email)->first() ?? User::first();

        // Khởi tạo quỹ phép cho năm hiện tại
        $year = (int) Carbon::today()->year;
        $balance = LeaveBalance::firstOrCreate([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'year' => $year,
            'leave_type' => LeaveType::ANNUAL->value,
        ], [
            'statutory_allocated_days' => 12.00,
            'total_entitled_days' => 12.00,
            'remaining_days' => 12.00,
            'used_days' => 0.00,
            'pending_days' => 0.00,
        ]);

        $initialRemaining = (float) $balance->remaining_days;

        $response = $this->actingAs($user)->post('/workflow/requests', [
            'employee_id' => $employee->id,
            'request_type' => RequestType::LEAVE->value,
            'form_payload' => [
                'leave_category' => 'ANNUAL',
                'start_date' => Carbon::today()->addDays(2)->toDateString(),
                'end_date' => Carbon::today()->addDays(3)->toDateString(),
                'days' => 2.0,
                'year' => $year,
                'reason' => 'Nghỉ giải quyết việc gia đình',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Kiểm tra đơn đã được tạo
        $req = ApprovalRequest::where('employee_id', $employee->id)
            ->where('request_type', RequestType::LEAVE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($req);
        $this->assertSame(RequestStatus::PENDING->value, $req->status);
        $this->assertSame(1, $req->current_step);
        $this->assertNotEmpty($req->snapshot_approvers);

        // Kiểm tra quỹ phép đã tạm giữ 2 ngày
        $balance->refresh();
        $this->assertEquals(2.00, (float) $balance->pending_days);
        $this->assertEquals($initialRemaining - 2.00, (float) $balance->remaining_days);
    }

    /**
     * Luồng phê duyệt đa cấp hoàn tất: Tự động trừ quỹ phép và tạo bản ghi biến động LeaveTransaction
     */
    public function test_multi_level_approval_flow_and_post_approval_leave_deduction(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $adminUser = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $approverEmp = Employee::where('tenant_id', $employee->tenant_id)
            ->where('id', '!=', $employee->id)
            ->first();

        $year = (int) Carbon::today()->year;
        $balance = LeaveBalance::firstOrCreate([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'year' => $year,
            'leave_type' => LeaveType::ANNUAL->value,
        ], [
            'statutory_allocated_days' => 12.00,
            'total_entitled_days' => 12.00,
            'remaining_days' => 12.00,
            'used_days' => 0.00,
            'pending_days' => 0.00,
        ]);

        // Tạo đơn xin nghỉ phép 1.5 ngày
        $workflowService = app(\App\Modules\Workflow\Services\WorkflowExecutionService::class);
        $req = $workflowService->createRequest(
            $employee->tenant_id,
            $employee->id,
            RequestType::LEAVE->value,
            [
                'leave_category' => 'ANNUAL',
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'days' => 1.5,
                'year' => $year,
                'reason' => 'Khám sức khỏe định kỳ',
            ]
        );

        $totalSteps = count($req->snapshot_approvers);

        // Duyệt từng bước cho tới bước cuối
        for ($step = 1; $step <= $totalSteps; $step++) {
            $response = $this->actingAs($adminUser)->post("/workflow/requests/{$req->id}/approve", [
                'comment' => "Đồng ý cấp duyệt {$step}",
            ]);
            $response->assertSessionHasNoErrors();
            $req->refresh();
        }

        // Đơn phải ở trạng thái APPROVED
        $this->assertSame(RequestStatus::APPROVED->value, $req->status);

        // Quỹ phép: pending_days giảm về 0, used_days tăng 1.5
        $balance->refresh();
        $this->assertEquals(0.00, (float) $balance->pending_days);
        $this->assertEquals(1.50, (float) $balance->used_days);

        // Phải có 1 LeaveTransaction loại USAGE
        $trans = LeaveTransaction::where('leave_balance_id', $balance->id)
            ->where('reference_id', $req->id)
            ->first();

        $this->assertNotNull($trans);
        $this->assertEquals(-1.50, (float) $trans->days);
    }

    /**
     * Từ chối đơn xin nghỉ phép: Tự động hoàn lại số ngày phép tạm giữ về số dư khả dụng
     */
    public function test_rejecting_leave_request_releases_pending_days(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $adminUser = User::where('role', UserRole::SUPER_ADMIN->value)->first();

        $year = (int) Carbon::today()->year;
        $balance = LeaveBalance::firstOrCreate([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'year' => $year,
            'leave_type' => LeaveType::ANNUAL->value,
        ], [
            'statutory_allocated_days' => 12.00,
            'total_entitled_days' => 12.00,
            'remaining_days' => 12.00,
            'used_days' => 0.00,
            'pending_days' => 0.00,
        ]);

        $workflowService = app(\App\Modules\Workflow\Services\WorkflowExecutionService::class);
        $req = $workflowService->createRequest(
            $employee->tenant_id,
            $employee->id,
            RequestType::LEAVE->value,
            [
                'leave_category' => 'ANNUAL',
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'days' => 3.0,
                'year' => $year,
                'reason' => 'Việc cá nhân',
            ]
        );

        $balance->refresh();
        $this->assertEquals(3.00, (float) $balance->pending_days);
        $this->assertEquals(9.00, (float) $balance->remaining_days);

        // Từ chối đơn
        $response = $this->actingAs($adminUser)->post("/workflow/requests/{$req->id}/reject", [
            'comment' => 'Thời điểm này phòng ban đang chạy tiến độ dự án, không thể duyệt nghỉ phép.',
        ]);

        $response->assertSessionHasNoErrors();
        $req->refresh();
        $this->assertSame(RequestStatus::REJECTED->value, $req->status);

        // Kiểm tra ngày phép đã được hoàn lại đầy đủ
        $balance->refresh();
        $this->assertEquals(0.00, (float) $balance->pending_days);
        $this->assertEquals(12.00, (float) $balance->remaining_days);
    }

    /**
     * Ràng buộc Điều 137 Khoản 1 Điểm b BLLĐ 2019: Lao động nuôi con < 12 tháng tuổi
     * Tuyệt đối cấm làm ca đêm / OT trừ khi có cam kết thỏa thuận tự nguyện
     */
    public function test_article_137_night_shift_requires_voluntary_consent(): void
    {
        $employee = Employee::where('gender', 'FEMALE')->first();
        $user = User::where('email', $employee->email)->first() ?? User::first();

        // Gắn 1 bé sơ sinh 6 tháng tuổi cho nhân sự này
        EmployeeChild::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'full_name' => 'Bé Nhỏ Test',
            'birth_date' => Carbon::today()->subMonths(6)->toDateString(),
            'is_active' => true,
        ]);

        // 1. Thử nộp đơn ca đêm mà KHÔNG có cam kết tự nguyện -> Bị từ chối
        $response = $this->actingAs($user)->post('/workflow/requests', [
            'employee_id' => $employee->id,
            'request_type' => RequestType::NIGHT_SHIFT->value,
            'form_payload' => [
                'work_date' => Carbon::today()->addDays(1)->toDateString(),
                'reason' => 'Đăng ký ca trực đêm',
            ],
            'is_voluntary_consent' => false,
        ]);

        $response->assertSessionHasErrors(['is_voluntary_consent']);

        // 2. Nộp đơn ca đêm CÓ cam kết tự nguyện và liên kết văn bản -> Thành công
        $responseSuccess = $this->actingAs($user)->post('/workflow/requests', [
            'employee_id' => $employee->id,
            'request_type' => RequestType::NIGHT_SHIFT->value,
            'form_payload' => [
                'work_date' => Carbon::today()->addDays(1)->toDateString(),
                'reason' => 'Tự nguyện đăng ký ca trực đêm',
            ],
            'is_voluntary_consent' => true,
            'voluntary_agreement_url' => 'https://drive.google.com/file/d/sample-consent-doc',
        ]);

        $responseSuccess->assertSessionHasNoErrors();

        $req = ApprovalRequest::where('employee_id', $employee->id)
            ->where('request_type', RequestType::NIGHT_SHIFT->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($req);
        $this->assertTrue((bool) $req->is_voluntary_consent);
        $this->assertSame('https://drive.google.com/file/d/sample-consent-doc', $req->voluntary_agreement_url);
    }

    /**
     * Cấp duyệt đối soát văn bản thỏa thuận tự nguyện Điều 137 khi phê duyệt
     */
    public function test_approver_can_verify_article_137_consent_and_record_history(): void
    {
        $employee = Employee::where('gender', 'FEMALE')->first();
        $adminUser = User::where('role', UserRole::SUPER_ADMIN->value)->first();

        // Gắn 1 bé sơ sinh 4 tháng tuổi
        EmployeeChild::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'full_name' => 'Bé Con Test',
            'birth_date' => Carbon::today()->subMonths(4)->toDateString(),
            'is_active' => true,
        ]);

        $workflowService = app(\App\Modules\Workflow\Services\WorkflowExecutionService::class);
        $req = $workflowService->createRequest(
            $employee->tenant_id,
            $employee->id,
            RequestType::NIGHT_SHIFT->value,
            [
                'work_date' => Carbon::today()->toDateString(),
                'reason' => 'Đổi ca hỗ trợ đồng nghiệp',
            ],
            true, // isVoluntaryConsent
            null,
            'https://storage.company.com/consent-signed.pdf'
        );

        // Cấp duyệt phê duyệt kèm tích chọn xác nhận đã đối soát văn bản
        $response = $this->actingAs($adminUser)->post("/workflow/requests/{$req->id}/approve", [
            'comment' => 'Đã đối soát bản cam kết có chữ ký của nhân viên.',
            'verified_child_under_12m_consent' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $history = ApprovalHistory::where('request_id', $req->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertTrue((bool) $history->verified_child_under_12m_consent);
    }

    /**
     * Ràng buộc Điều 107 BLLĐ 2019: Giờ làm thêm trong ngày không vượt quá 4h (50% ngày công thường)
     */
    public function test_overtime_daily_max_4_hours_constraint(): void
    {
        $employee = Employee::where('user_code', 'EMP001')->first();
        $user = User::where('email', $employee->email)->first() ?? User::first();

        // 1. Nộp đơn làm thêm 5.5h trong ngày -> Bị chặn
        $responseInvalid = $this->actingAs($user)->post('/workflow/requests', [
            'employee_id' => $employee->id,
            'request_type' => RequestType::OVERTIME->value,
            'form_payload' => [
                'date' => Carbon::today()->toDateString(),
                'hours' => 5.5,
                'reason' => 'Tăng ca sửa máy',
            ],
        ]);

        $responseInvalid->assertSessionHasErrors(['hours']);

        // 2. Nộp đơn làm thêm 3.0h -> Hợp lệ và sau khi duyệt cộng dồn vào overtime_accumulations
        $responseValid = $this->actingAs($user)->post('/workflow/requests', [
            'employee_id' => $employee->id,
            'request_type' => RequestType::OVERTIME->value,
            'form_payload' => [
                'date' => Carbon::today()->toDateString(),
                'hours' => 3.0,
                'reason' => 'Hỗ trợ kiểm kê kho định kỳ',
            ],
        ]);

        $responseValid->assertSessionHasNoErrors();

        $req = ApprovalRequest::where('employee_id', $employee->id)
            ->where('request_type', RequestType::OVERTIME->value)
            ->latest('id')
            ->first();

        // Admin duyệt toàn bộ
        $adminUser = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        for ($i = 0; $i < count($req->snapshot_approvers); $i++) {
            $this->actingAs($adminUser)->post("/workflow/requests/{$req->id}/approve", [
                'comment' => 'Duyệt OT',
            ]);
            $req->refresh();
        }

        $this->assertSame(RequestStatus::APPROVED->value, $req->status);

        // Kiểm tra lũy kế giờ OT trong năm
        $otAcc = OvertimeAccumulation::where('employee_id', $employee->id)
            ->where('year', (int) Carbon::today()->year)
            ->first();

        $this->assertNotNull($otAcc);
        $this->assertGreaterThanOrEqual(3.00, (float) $otAcc->total_ot_hours_year);
    }

    /**
     * Cấu hình quy trình phê duyệt linh hoạt theo Zero-Hardcode policy
     */
    public function test_workflow_definition_update_zero_hardcode(): void
    {
        $adminUser = User::where('role', UserRole::SUPER_ADMIN->value)->first();
        $tenantId = (int) $adminUser->tenant_id;

        $response = $this->actingAs($adminUser)->post('/workflow/configs', [
            'request_type' => RequestType::LEAVE->value,
            'total_levels' => 3,
            'steps_config' => [
                ['level' => 1, 'role_type' => 'DIRECT_MANAGER', 'title' => 'Trưởng nhóm trực tiếp'],
                ['level' => 2, 'role_type' => 'DEPARTMENT_HEAD', 'title' => 'Trưởng phòng ban'],
                ['level' => 3, 'role_type' => 'HR_ADMIN', 'title' => 'Giám đốc Nhân sự'],
            ],
            'condition_rules' => [
                'min_days_for_level_2' => 3,
            ],
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $def = WorkflowDefinition::where('tenant_id', $tenantId)
            ->where('request_type', RequestType::LEAVE->value)
            ->first();

        $this->assertNotNull($def);
        $this->assertSame(3, $def->total_levels);
        $this->assertCount(3, $def->steps_config);
        $this->assertSame('Giám đốc Nhân sự', $def->steps_config[2]['title']);
    }
}
