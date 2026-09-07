<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Compliance\Services\ComplianceScannerService;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\DepartmentVersion;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\Notification\Models\AppNotification;
use App\Modules\Notification\Models\NotificationChannelConfig;
use App\Modules\Notification\Services\NotificationDispatcherService;
use App\Modules\Workflow\Enums\RequestType;
use App\Modules\Workflow\Services\WorkflowExecutionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationEngineEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected Branch $branch;
    protected Department $department;
    protected Position $position;
    protected Position $managerPosition;
    protected Employee $employee1;
    protected Employee $managerEmployee;
    protected User $user1;
    protected User $managerUser;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo Tenant & Branch
        $this->tenant = Tenant::create([
            'name' => 'Công Ty Sao Vàng Notification',
            'code' => 'NOTI_GOLD_STAR',
            'domain' => 'noti-gold-star.smarthr.vn',
            'status' => 'ACTIVE',
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Công Ty Khác Tenant B',
            'code' => 'OTHER_TENANT_B',
            'domain' => 'other-tenant-b.smarthr.vn',
            'status' => 'ACTIVE',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_HQ',
            'name' => 'Trụ sở Hà Nội',
            'secret_key' => 'noti_gold_star_secret_123',
            'is_remote_or_island' => false,
        ]);

        // 2. Tạo Phòng ban & Chức danh
        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'code' => 'DEPT_OPS',
            'name' => 'Phòng Vận Hành',
            'level' => 1,
            'is_active' => true,
        ]);

        DepartmentVersion::create([
            'department_id' => $this->department->id,
            'name' => 'Phòng Vận Hành Hệ Thống',
            'effective_date' => '2025-01-01',
        ]);

        $this->position = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'code' => 'STAFF_OPS',
            'name' => 'Chuyên viên Vận hành',
            'level' => 1,
        ]);

        $this->managerPosition = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'code' => 'MGR_OPS',
            'name' => 'Trưởng phòng Vận hành',
            'level' => 2,
        ]);

        // 3. Tạo Nhân viên thông thường
        $this->employee1 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP_NOTI_01',
            'full_name' => 'Nguyễn Văn Minh',
            'gender' => 'MALE',
            'birth_date' => '1992-04-12',
            'email' => 'minh.nguyen@notigoldstar.com',
            'join_date' => '2025-01-01',
            'status' => 'OFFICIAL',
        ]);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->employee1->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'job_title' => 'Chuyên viên Vận hành',
            'effective_date' => '2025-01-01',
        ]);

        $this->user1 = User::create([
            'name' => 'Nguyễn Văn Minh',
            'email' => 'minh.nguyen@notigoldstar.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::EMPLOYEE->value,
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
        ]);

        // 4. Tạo Quản lý (Manager)
        $this->managerEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'MGR_NOTI_01',
            'full_name' => 'Trần Văn Quản Lý',
            'gender' => 'MALE',
            'birth_date' => '1985-08-15',
            'email' => 'manager@notigoldstar.com',
            'join_date' => '2024-01-01',
            'status' => 'OFFICIAL',
        ]);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->managerEmployee->id,
            'event_type' => 'ONBOARDING',
            'department_id' => $this->department->id,
            'position_id' => $this->managerPosition->id,
            'job_title' => 'Trưởng phòng Vận hành',
            'effective_date' => '2024-01-01',
        ]);

        $this->managerUser = User::create([
            'name' => 'Trần Văn Quản Lý',
            'email' => 'manager@notigoldstar.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::DEPARTMENT_MANAGER->value,
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->managerEmployee->id,
        ]);

        // Gán manager cho phòng ban
        $this->department->update(['manager_id' => $this->managerEmployee->id]);

        // 5. Tạo Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin Noti',
            'email' => 'admin@notigoldstar.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::SUPER_ADMIN->value,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test 1: Gửi thông báo đến một người dùng cụ thể tạo bản ghi In-App chính xác
     */
    public function test_send_notification_to_user_creates_in_app_record(): void
    {
        /** @var NotificationDispatcherService $dispatcher */
        $dispatcher = app(NotificationDispatcherService::class);

        $notification = $dispatcher->sendToUser(
            tenantId: $this->tenant->id,
            userId: $this->user1->id,
            type: 'ROSTER_PUBLISHED',
            title: 'Lịch phân ca tuần mới',
            content: 'Lịch phân ca tuần 38 đã được công bố. Vui lòng kiểm tra ca làm việc của bạn.',
            actionUrl: '/rosters',
            payload: ['week' => 38],
            severity: 'INFO',
            employeeId: $this->employee1->id
        );

        $this->assertNotNull($notification->id);
        $this->assertEquals($this->user1->id, $notification->user_id);
        $this->assertEquals('ROSTER_PUBLISHED', $notification->type);
        $this->assertEquals('INFO', $notification->severity);
        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);
        $this->assertContains('in_app', $notification->channels_sent);

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notification->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user1->id,
            'is_read' => false,
        ]);
    }

    /**
     * Test 2: Broadcast thông báo theo vai trò tạo bản ghi cho tất cả người dùng thuộc vai trò
     */
    public function test_broadcast_notification_to_role_creates_records_for_role_users(): void
    {
        // Tạo thêm 1 manager nữa
        User::create([
            'name' => 'Quản lý 2',
            'email' => 'manager2@notigoldstar.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::DEPARTMENT_MANAGER->value,
            'tenant_id' => $this->tenant->id,
        ]);

        /** @var NotificationDispatcherService $dispatcher */
        $dispatcher = app(NotificationDispatcherService::class);

        $count = $dispatcher->broadcastToRole(
            tenantId: $this->tenant->id,
            roles: [UserRole::DEPARTMENT_MANAGER->value],
            type: 'TIMESHEET_SUBMITTED',
            title: 'Bảng công tháng cần duyệt',
            content: 'Đã có 5 nhân sự nộp bảng công tháng 09/2026 cần phê duyệt.',
            actionUrl: '/attendance',
            severity: 'WARNING'
        );

        $this->assertEquals(2, $count);

        $this->assertDatabaseCount('app_notifications', 2);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->managerUser->id,
            'type' => 'TIMESHEET_SUBMITTED',
        ]);
    }

    /**
     * Test 3: Đánh dấu một thông báo đã đọc cập nhật is_read và read_at
     */
    public function test_mark_single_notification_as_read(): void
    {
        $notification = AppNotification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user1->id,
            'type' => 'WORKFLOW_APPROVED',
            'severity' => 'SUCCESS',
            'title' => 'Đơn xin nghỉ phép đã duyệt',
            'content' => 'Đơn xin nghỉ ngày 15/09 của bạn đã được duyệt.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user1)->post("/notifications/{$notification->id}/read");

        $response->assertStatus(302);
        $notification->refresh();

        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test 4: Đánh dấu tất cả thông báo của người dùng đã đọc
     */
    public function test_mark_all_notifications_as_read(): void
    {
        AppNotification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user1->id,
            'type' => 'TYPE_A',
            'severity' => 'INFO',
            'title' => 'Noti 1',
            'content' => 'Content 1',
            'is_read' => false,
        ]);

        AppNotification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user1->id,
            'type' => 'TYPE_B',
            'severity' => 'INFO',
            'title' => 'Noti 2',
            'content' => 'Content 2',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user1)->post('/notifications/read-all');

        $response->assertStatus(302);

        $unreadCount = AppNotification::forUser($this->user1->id)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test 5: Xóa thông báo thành công
     */
    public function test_dismiss_notification_deletes_record(): void
    {
        $notification = AppNotification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user1->id,
            'type' => 'TYPE_TO_DELETE',
            'severity' => 'INFO',
            'title' => 'Xóa thông báo',
            'content' => 'Nội dung',
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user1)->delete("/notifications/{$notification->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('app_notifications', ['id' => $notification->id]);
    }

    /**
     * Test 6: Tạo đơn phê duyệt tự động phát thông báo đến cấp duyệt
     */
    public function test_workflow_creation_dispatches_notification_to_approver(): void
    {
        /** @var WorkflowExecutionService $workflowService */
        $workflowService = app(WorkflowExecutionService::class);

        $request = $workflowService->createRequest(
            tenantId: $this->tenant->id,
            employeeId: $this->employee1->id,
            requestType: RequestType::REMOTE_WORK->value,
            payload: [
                'title' => 'Đề xuất làm việc từ xa',
                'reason' => 'Xử lý công việc dự án tại nhà',
            ]
        );

        $this->assertNotNull($request->id);

        // Kiểm tra thông báo được gửi đến managerUser
        $notification = AppNotification::where('user_id', $this->managerUser->id)
            ->where('type', 'WORKFLOW_PENDING_APPROVAL')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Nguyễn Văn Minh', $notification->title);
        $this->assertEquals('/workflow/requests?tab=to_approve', $notification->action_url);
    }

    /**
     * Test 7: Phê duyệt đơn tự động phát thông báo kết quả đến người tạo đơn
     */
    public function test_workflow_approval_dispatches_notification_to_submitter(): void
    {
        /** @var WorkflowExecutionService $workflowService */
        $workflowService = app(WorkflowExecutionService::class);

        $request = $workflowService->createRequest(
            tenantId: $this->tenant->id,
            employeeId: $this->employee1->id,
            requestType: RequestType::REMOTE_WORK->value,
            payload: ['title' => 'Đăng ký làm việc từ xa', 'reason' => 'Việc cá nhân']
        );

        // Quản lý duyệt đơn
        $workflowService->approveStep(
            request: $request,
            approver: $this->managerEmployee,
            comment: 'Đồng ý duyệt đề xuất',
            actorUser: $this->managerUser
        );

        // Kiểm tra thông báo kết quả gửi về cho user1
        $notification = AppNotification::where('user_id', $this->user1->id)
            ->where('type', 'WORKFLOW_APPROVED')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('SUCCESS', $notification->severity);
        $this->assertStringContainsString('đã được phê duyệt', $notification->title);
    }

    /**
     * Test 8: Phát hiện vi phạm BLLĐ Critical tự động gửi cảnh báo khẩn cấp cho Quản trị viên
     */
    public function test_compliance_critical_alert_dispatches_notification_to_admins(): void
    {
        /** @var ComplianceScannerService $scanner */
        $scanner = app(ComplianceScannerService::class);

        // Giả lập quét vi phạm trần OT tháng 40h
        // Tích lũy OT tháng vượt 40h
        \App\Modules\LeavePolicy\Models\OvertimeAccumulation::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'year' => 2026,
            'monthly_ot_json' => ['2026-09' => 45.0],
            'total_ot_hours_year' => 120.0,
        ]);

        $scanner->scanOvertimeCaps($this->tenant->id, Carbon::parse('2026-09-20'));

        // Kiểm tra thông báo gửi đến superAdmin
        $notification = AppNotification::where('user_id', $this->superAdmin->id)
            ->where('type', 'COMPLIANCE_CRITICAL_ALERT')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('CRITICAL', $notification->severity);
        $this->assertStringContainsString('45', $notification->content);
        $this->assertEquals('/compliance', $notification->action_url);
    }

    /**
     * Test 9: Cách ly Tenant không cho phép xem thông báo của Tenant khác
     */
    public function test_tenant_isolation_prevents_viewing_other_tenant_notifications(): void
    {
        // Tạo thông báo cho Tenant B
        $userTenantB = User::create([
            'name' => 'User B',
            'email' => 'userb@other.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::EMPLOYEE->value,
            'tenant_id' => $this->otherTenant->id,
        ]);

        $notiTenantB = AppNotification::create([
            'tenant_id' => $this->otherTenant->id,
            'user_id' => $userTenantB->id,
            'type' => 'TYPE_B',
            'severity' => 'INFO',
            'title' => 'Noti Tenant B',
            'content' => 'Content Tenant B',
            'is_read' => false,
        ]);

        // user1 của Tenant A cố gắng đánh dấu đọc thông báo của Tenant B
        $response = $this->actingAs($this->user1)->post("/notifications/{$notiTenantB->id}/read");
        $response->assertStatus(302);

        // Thông báo của Tenant B vẫn chưa đọc
        $notiTenantB->refresh();
        $this->assertFalse($notiTenantB->is_read);
    }

    /**
     * Test 10: Lưu và nạp cấu hình kênh gửi mở rộng (Telegram / Webhook)
     */
    public function test_save_and_retrieve_channel_configuration(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/notifications/channels/save', [
            'channel' => 'TELEGRAM',
            'is_active' => true,
            'settings_json' => [
                'bot_token' => '123456789:ABC_demo_bot_token',
                'chat_id' => '-100987654321',
            ],
            'event_subscriptions' => [
                'COMPLIANCE_CRITICAL_ALERT',
                'WORKFLOW_PENDING_APPROVAL',
            ],
        ]);

        $response->assertStatus(302);

        $config = NotificationChannelConfig::where('tenant_id', $this->tenant->id)
            ->where('channel', 'TELEGRAM')
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->is_active);
        $this->assertEquals('123456789:ABC_demo_bot_token', $config->settings_json['bot_token']);
        $this->assertContains('COMPLIANCE_CRITICAL_ALERT', $config->event_subscriptions);
    }

    /**
     * Test 11: Màn hình /notifications render thành công qua Inertia
     */
    public function test_notification_index_screen_renders_successfully(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/notifications');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Notification/Index')
            ->has('notifications.data')
            ->has('stats.total')
            ->has('stats.unread')
            ->has('filters')
            ->has('channelConfigs')
            ->has('canManageChannels')
            ->has('eventTypes')
        );
    }

    /**
     * Test 12: Endpoint thử nghiệm kênh gửi ngoài (Telegram / Webhook) hoạt động
     */
    public function test_channel_test_endpoint_dispatches_external_message(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/notifications/channels/test', [
            'channel' => 'TELEGRAM',
            'settings_json' => [
                'bot_token' => '123456:FAKE_TOKEN',
                'chat_id' => '12345678',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }
}
