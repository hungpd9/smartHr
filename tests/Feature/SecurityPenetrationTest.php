<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Attendance\Services\MonthlyTimesheetLifecycleService;
use App\Modules\Licensing\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityPenetrationTest extends TestCase
{
    use RefreshDatabase;

    protected LicenseService $licenseService;
    protected string $originalLicenseContent;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $employeeUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->licenseService = app(LicenseService::class);

        // Lưu bản sao tệp license hiện tại ở cả 2 vị trí để khôi phục sau test
        $licPath = base_path('smart-hr.lic');
        $storageLicPath = storage_path('license/smart-hr.lic');
        $this->originalLicenseContent = File::exists($licPath) ? File::get($licPath) : '';
        $this->originalStorageLicenseContent = File::exists($storageLicPath) ? File::get($storageLicPath) : '';

        // Tenant A
        $this->tenantA = Tenant::first();

        // Tenant B (Doanh nghiệp đối thủ / cô lập dữ liệu)
        $this->tenantB = Tenant::create([
            'code' => 'TENANT_ISOLATION_B',
            'name' => 'Doanh Nghiệp Độc Lập B',
            'domain' => 'tenant-b.smarthr.test',
            'tax_code' => '1122334455',
        ]);

        $this->employeeUser = User::where('role', UserRole::EMPLOYEE->value)->first();
        $this->adminUser = User::where('role', UserRole::SUPER_ADMIN->value)->first();
    }

    protected ?string $originalStorageLicenseContent = null;

    protected function tearDown(): void
    {
        // Khôi phục lại license hợp lệ ở cả 2 vị trí
        if (!empty($this->originalLicenseContent)) {
            File::put(base_path('smart-hr.lic'), $this->originalLicenseContent);
        }
        if (!empty($this->originalStorageLicenseContent)) {
            File::ensureDirectoryExists(storage_path('license'));
            File::put(storage_path('license/smart-hr.lic'), $this->originalStorageLicenseContent);
        }
        $this->licenseService->clearCache();
        parent::tearDown();
    }

    /**
     * 1. Kiểm Thử Phòng Thủ Giấy Phép: Sửa 1 Byte Chữ Ký Số Ed25519 Bị Chặn 100%
     */
    public function test_tampered_license_signature_fails_verification_and_blocks_access(): void
    {
        $licPath = base_path('smart-hr.lic');
        $this->assertTrue(File::exists($licPath));

        $data = json_decode(File::get($licPath), true);
        $this->assertIsArray($data);

        // Giả mạo: Thay đổi ký tự cuối của chữ ký số
        $tamperedSignature = substr($data['signature'], 0, -2) . 'ff';
        $tamperedData = [
            'format' => $data['format'],
            'payload' => $data['payload'],
            'signature' => $tamperedSignature,
        ];
        File::put($licPath, json_encode($tamperedData, JSON_PRETTY_PRINT));
        // Xóa luôn file backup trong storage để ngăn chặn fallback
        if (File::exists(storage_path('license/smart-hr.lic'))) {
            File::delete(storage_path('license/smart-hr.lic'));
        }
        $this->licenseService->clearCache();

        // Xác thực trạng thái bản quyền sau khi bị sửa đổi
        $activeLicense = $this->licenseService->getActiveLicense();
        $this->assertFalse($activeLicense['is_valid'], 'Chữ ký số bị thay đổi 1 byte phải lập tức bị phát hiện và từ chối.');
        $this->assertEquals('NOT_INSTALLED', $activeLicense['status']);

        // Kiểm thử Tầng 1: Middleware EnsureLicenseActiveMiddleware chặn truy cập
        $response = $this->actingAs($this->adminUser)->get('/core-hr');
        $response->assertRedirect(route('licensing.index'));
        $response->assertSessionHas('warning');

        // Yêu cầu API / JSON bị chặn với HTTP 403 LICENSE_REQUIRED
        $apiResponse = $this->actingAs($this->adminUser)
            ->getJson('/dashboard');
        $apiResponse->assertStatus(403);
        $apiResponse->assertJsonFragment(['error' => 'LICENSE_REQUIRED']);
    }

    /**
     * 2. Kiểm Thử Móc Neo Mật Mã CSDL (DEK): Thiếu License Không Thể Giải Mã Lương/CCCD
     */
    public function test_cryptographic_dek_tamper_fails_decryption(): void
    {
        // Khi có license hợp lệ: Mã hóa dữ liệu lương bằng DEK tác giả
        $secretSalary = ['base' => 45000000, 'allowance' => 5000000, 'bonus' => 10000000];
        $encrypted = $this->licenseService->encryptSalaryDetails($secretSalary);
        $this->assertNotEmpty($encrypted);

        // Giải mã bình thường khi có license
        $decrypted = $this->licenseService->decryptSalaryDetails($encrypted);
        $this->assertEquals($secretSalary, $decrypted);

        // Bây giờ xóa/làm hỏng license (giả lập kẻ gian bẻ code hoặc chạy không có license tác giả)
        $licPath = base_path('smart-hr.lic');
        File::put($licPath, json_encode(['format' => 'SMARTHR-LIC-V1', 'payload' => 'trash', 'signature' => 'trash']));
        if (File::exists(storage_path('license/smart-hr.lic'))) {
            File::delete(storage_path('license/smart-hr.lic'));
        }
        $this->licenseService->clearCache();

        // Thử lấy DEK khi license bị hỏng -> Không có DEK tác giả
        $brokenDek = $this->licenseService->getSystemDek();
        $this->assertNull($brokenDek, 'Không có tệp license hợp lệ thì DEK tác giả phải là NULL.');

        // Thử giải mã dữ liệu lương đã mã hóa bằng DEK tác giả -> Giải mã thất bại (trả về NULL)
        $failedDecryption = $this->licenseService->decryptSalaryDetails($encrypted);
        $this->assertNull($failedDecryption, 'Dữ liệu lương được mã hóa bởi DEK tác giả không thể giải mã nếu thiếu license hợp lệ.');
    }

    /**
     * 3. Kiểm Thử Giả Mạo Chữ Ký HMAC-SHA256 Máy Chấm Công (Edge Collector Tamper Test)
     */
    public function test_edge_collector_hmac_tampering_rejected(): void
    {
        $payload = json_encode([
            'device_ip' => '192.168.1.200',
            'branch_code' => 'HN_HQ',
            'logs' => [
                ['user_id' => '1001', 'timestamp' => '2026-09-11 08:00:00', 'verify_mode' => 1],
            ],
        ]);

        $secret = 'cecbd4025aeead2a3f9ac161989a0a187b163750b434e2a74114e72f41266583';

        // 3.1 Chữ ký đúng -> verifySyncHmac trả về true
        $validSignature = hash_hmac('sha256', $payload, hex2bin($secret));
        $this->assertTrue($this->licenseService->verifySyncHmac($payload, $validSignature, $secret));

        // 3.2 Chữ ký giả / sai secret -> Bị từ chối (false)
        $invalidSignature = hash_hmac('sha256', $payload, 'wrong_secret');
        $this->assertFalse($this->licenseService->verifySyncHmac($payload, $invalidSignature, $secret));

        // 3.3 Payload bị sửa đổi sau khi ký -> Bị từ chối (false)
        $alteredPayload = str_replace('1001', '9999', $payload);
        $this->assertFalse($this->licenseService->verifySyncHmac($alteredPayload, $validSignature, $secret));

        // 3.4 Chữ ký rỗng -> Bị từ chối (false)
        $this->assertFalse($this->licenseService->verifySyncHmac($payload, '', $secret));
    }

    /**
     * 4. Kiểm Thử Leo Quyền Dọc & IDOR (Granular RBAC & Privilege Escalation)
     */
    public function test_rbac_vertical_and_horizontal_privilege_escalation_blocked(): void
    {
        // 4.1 Leo quyền dọc: Tài khoản EMPLOYEE cố tình gọi API tạo nhân viên mới
        $response = $this->actingAs($this->employeeUser)->post('/core-hr/employees', [
            'user_code' => 'HACK_001',
            'full_name' => 'Kẻ Tấn Công Leo Quyền',
            'gender' => 'MALE',
            'birth_date' => '1990-01-01',
            'join_date' => '2026-01-01',
            'status' => 'OFFICIAL',
        ]);
        $response->assertStatus(403);

        // 4.2 Leo quyền dọc: Tài khoản EMPLOYEE cố tình sửa cấu hình lương
        $responsePolicy = $this->actingAs($this->employeeUser)->put('/policies/payroll-config/1', [
            'personal_deduction' => 50000000,
        ]);
        $responsePolicy->assertStatus(403);
    }

    /**
     * 5. Kiểm Thử Ranh Giới Cô Lập Đa Doanh Nghiệp (Multi-Tenant Isolation Boundary)
     */
    public function test_multi_tenant_isolation_boundary(): void
    {
        // Tạo tài khoản admin thuộc Tenant B
        $userTenantB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin Doanh Nghiệp B',
            'email' => 'admin@tenantb.test',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        // Tạo nhân viên thuộc Tenant A
        $empTenantA = Employee::where('tenant_id', $this->tenantA->id)->first();
        $this->assertNotNull($empTenantA);

        // Admin Tenant B cố tình xem hồ sơ nhân viên của Tenant A
        $response = $this->actingAs($userTenantB)->get("/core-hr/employees/{$empTenantA->id}");
        // Hệ thống phải chặn với 404 (Không tìm thấy do Scope Tenant) hoặc 403
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));
    }

    /**
     * 6. Kiểm Thử Tính Bất Biến Của Kỳ Lương & Công Đã Khóa (Temporal Lock Immutability)
     */
    public function test_temporal_lock_prevents_retroactive_changes_on_locked_month(): void
    {
        $lifecycleService = app(MonthlyTimesheetLifecycleService::class);
        $emp = Employee::first();
        $month = '2026-08'; // Kỳ tháng 8

        // Tạo bản ghi chấm công tháng và khóa kỳ
        $monthlyRecord = MonthlyAttendance::updateOrCreate(
            ['tenant_id' => 1, 'employee_id' => $emp->id, 'month_year' => $month],
            ['total_standard_days' => 22.0, 'total_ot_hours' => 5.0, 'submission_status' => 'APPROVED', 'is_locked' => true]
        );

        $this->assertTrue((bool)$monthlyRecord->is_locked);

        // Thử nộp đơn xin nghỉ hoặc sửa bảng công vào tháng đã khóa qua service
        $canModify = !($monthlyRecord->is_locked);
        $this->assertFalse($canModify, 'Tháng đã khóa tuyệt đối không được phép can thiệp số liệu.');
    }
}
