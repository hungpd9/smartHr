<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Attendance\Models\RawAttendanceLog;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\Licensing\Services\LicenseService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LicensingAndCryptographicAnchoringTest extends TestCase
{
    use RefreshDatabase;

    protected LicenseService $licenseService;
    protected Tenant $tenant;
    protected Branch $branch;
    protected Employee $employee;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->licenseService = app(LicenseService::class);

        $this->tenant = Tenant::create([
            'code' => 'TENANT_LIC_TEST',
            'name' => 'Tập đoàn Smart-HR Kiểm Thử',
            'domain' => 'lic-test.smarthr.vn',
            'tax_code' => '0102030405',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'HN_HQ',
            'name' => 'Trụ sở Hà Nội',
            'secret_key' => 'branch_secret_hmac_123',
            'is_active' => true,
        ]);

        $dept = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'code' => 'DEPT_IT',
            'status' => 'ACTIVE',
        ]);

        $pos = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $dept->id,
            'code' => 'DEV_SENIOR',
            'name' => 'Kỹ sư Phần mềm Cao cấp',
            'base_salary_standard' => 30000000,
        ]);

        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP001',
            'attendance_code' => '1001',
            'full_name' => 'Nguyễn Văn Kiểm Thử',
            'gender' => 'MALE',
            'birth_date' => '1992-05-10',
            'email' => 'test.emp@smarthr.vn',
            'join_date' => '2023-01-01',
            'citizen_id' => '001202009999',
            'status' => 'OFFICIAL',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Bản Quyền',
            'email' => 'admin.lic@smarthr.vn',
            'password' => bcrypt('Password123!'),
            'role' => 'SUPER_ADMIN',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * 1. Kiểm thử sinh cặp khóa Ed25519
     */
    public function test_author_can_generate_valid_ed25519_keypair(): void
    {
        $keys = $this->licenseService->generateKeyPair();

        $this->assertArrayHasKey('public_key', $keys);
        $this->assertArrayHasKey('private_key', $keys);
        $this->assertEquals(64, strlen($keys['public_key'])); // 32 bytes hex = 64 ký tự
        $this->assertEquals(128, strlen($keys['private_key'])); // 64 bytes hex = 128 ký tự
    }

    /**
     * 2. Kiểm thử tạo và xác thực tệp giấy phép bản quyền hợp lệ
     */
    public function test_create_and_verify_valid_license_file(): void
    {
        $keys = $this->licenseService->generateKeyPair();

        $licenseJson = $this->licenseService->createLicense(
            domain: 'hrm.testcompany.vn',
            customerName: 'Công ty Cổ phần Alpha',
            expiresAt: date('Y-m-d', strtotime('+1 year')),
            privateKeyHex: $keys['private_key']
        );

        $verified = $this->licenseService->verifyLicense($licenseJson, $keys['public_key']);

        $this->assertNotNull($verified);
        $this->assertEquals('hrm.testcompany.vn', $verified['allowed_domain']);
        $this->assertEquals('Công ty Cổ phần Alpha', $verified['customer_name']);
        $this->assertFalse($verified['is_expired']);
        $this->assertGreaterThan(300, $verified['days_remaining']);
        $this->assertEquals(64, strlen($verified['shared_hmac_secret'])); // 32 bytes hex
    }

    /**
     * 3. Kiểm thử từ chối giấy phép bị chỉnh sửa payload hoặc chữ ký giả mạo
     */
    public function test_tampered_license_is_rejected(): void
    {
        $keys = $this->licenseService->generateKeyPair();

        $licenseJson = $this->licenseService->createLicense(
            domain: 'hrm.legit.com',
            customerName: 'Công ty Hợp Pháp',
            expiresAt: date('Y-m-d', strtotime('+1 year')),
            privateKeyHex: $keys['private_key']
        );

        $data = json_decode($licenseJson, true);
        // Can thiệp sửa payload gian lận domain
        $payload = json_decode(base64_decode($data['payload']), true);
        $payload['allowed_domain'] = 'hrm.hacker.com';
        $data['payload'] = base64_encode(json_encode($payload));

        $tamperedJson = json_encode($data);

        $verified = $this->licenseService->verifyLicense($tamperedJson, $keys['public_key']);
        $this->assertNull($verified, 'Tệp bản quyền bị can thiệp payload phải bị từ chối.');
    }

    /**
     * 4. Kiểm thử nhận diện chính xác giấy phép đã hết hạn
     */
    public function test_expired_license_detection(): void
    {
        $keys = $this->licenseService->generateKeyPair();

        $pastDate = date('Y-m-d', strtotime('-10 days'));
        $licenseJson = $this->licenseService->createLicense(
            domain: 'hrm.expired.com',
            customerName: 'Khách Hàng Quá Hạn',
            expiresAt: $pastDate,
            privateKeyHex: $keys['private_key']
        );

        $verified = $this->licenseService->verifyLicense($licenseJson, $keys['public_key']);

        $this->assertNotNull($verified);
        $this->assertTrue($verified['is_expired']);
        $this->assertEquals(0, $verified['days_remaining']);
    }

    /**
     * 5. Kiểm thử dẫn xuất DEK qua HKDF-SHA256 (Cryptographic Data Anchoring)
     */
    public function test_cryptographic_data_anchoring_dek_derivation(): void
    {
        $signatureHex = bin2hex(random_bytes(64)); // Ed25519 signature 64 bytes
        $domainA = 'hrm.company-a.com';
        $domainB = 'hrm.company-b.com';

        $dekA = $this->licenseService->deriveDataEncryptionKey($signatureHex, $domainA);
        $dekB = $this->licenseService->deriveDataEncryptionKey($signatureHex, $domainB);

        $this->assertEquals(32, strlen($dekA)); // Khóa 256-bit
        $this->assertEquals(32, strlen($dekB));
        $this->assertNotEquals($dekA, $dekB, 'Khóa DEK cho 2 tên miền khác nhau phải hoàn toàn khác biệt.');
    }

    /**
     * 6. Kiểm thử mã hóa và giải mã CCCD qua AES-256-GCM
     */
    public function test_encrypt_and_decrypt_citizen_id_with_dek(): void
    {
        $citizenId = '001202008888';
        $domain = 'hrm.smarthr.vn';

        $ciphertext = $this->licenseService->encryptCitizenId($citizenId, $domain);
        $this->assertNotEmpty($ciphertext);
        $this->assertNotEquals($citizenId, $ciphertext);

        $decrypted = $this->licenseService->decryptCitizenId($ciphertext, $domain);
        $this->assertEquals($citizenId, $decrypted, 'Dữ liệu giải mã phải khớp 100% với số CCCD ban đầu.');
    }

    /**
     * 7. Kiểm thử giải mã thất bại khi sử dụng sai domain (DEK không khớp)
     */
    public function test_decrypt_fails_with_wrong_domain_salt(): void
    {
        $citizenId = '001202008888';
        $correctDomain = 'hrm.company-correct.vn';
        $wrongDomain = 'hrm.attacker-domain.vn';

        $ciphertext = $this->licenseService->encryptCitizenId($citizenId, $correctDomain);
        $decrypted = $this->licenseService->decryptCitizenId($ciphertext, $wrongDomain);

        $this->assertNull($decrypted, 'Giải mã bằng sai domain phải trả về null.');
    }

    /**
     * 8. Kiểm thử mã hóa và giải mã chi tiết lương (Salary Details)
     */
    public function test_encrypt_and_decrypt_salary_details(): void
    {
        $salaryData = [
            'base_salary' => 25000000,
            'position_allowance' => 3000000,
            'lunch_allowance' => 1000000,
            'bank_account' => '19038472910283',
        ];

        $ciphertext = $this->licenseService->encryptSalaryDetails($salaryData);
        $this->assertNotEmpty($ciphertext);

        $decryptedData = $this->licenseService->decryptSalaryDetails($ciphertext);
        $this->assertIsArray($decryptedData);
        $this->assertEquals(25000000, $decryptedData['base_salary']);
        $this->assertEquals('19038472910283', $decryptedData['bank_account']);
    }

    /**
     * 9. Kiểm thử tiếp nhận batch log quẹt thẻ từ Go Edge Daemon có HMAC-SHA256 hợp lệ
     */
    public function test_edge_collector_batch_sync_api_succeeds_with_valid_hmac(): void
    {
        $keys = $this->licenseService->generateKeyPair();
        $licContent = $this->licenseService->createLicense(
            domain: 'hrm.smarthr.vn',
            customerName: 'Kiểm thử Edge Sync',
            expiresAt: date('Y-m-d', strtotime('+1 year')),
            privateKeyHex: $keys['private_key']
        );

        $verified = $this->licenseService->verifyLicense($licContent, $keys['public_key']);
        $sharedSecret = $verified['shared_hmac_secret'];

        // Lưu tạm file license vào storage/license/ để controller nạp
        $licPath = storage_path('license/smart-hr.lic');
        File::ensureDirectoryExists(dirname($licPath));
        File::put($licPath, $licContent);

        // Gói tin payload từ Golang Edge Daemon
        $syncPayload = [
            'tenant_domain' => 'hrm.smarthr.vn',
            'branch_code' => $this->branch->code,
            'batch_id' => 'BATCH_TEST_001',
            'punches' => [
                [
                    'user_code' => 'EMP001',
                    'punch_time' => '2026-09-07 08:00:15',
                    'punch_state' => 'CHECK_IN',
                    'verification_mode' => 'FINGERPRINT',
                    'device_sn' => 'ZK-K40-LAN-01',
                ],
                [
                    'user_code' => 'EMP001',
                    'punch_time' => '2026-09-07 17:30:20',
                    'punch_state' => 'CHECK_OUT',
                    'verification_mode' => 'FINGERPRINT',
                    'device_sn' => 'ZK-K40-LAN-01',
                ],
            ],
            'sent_at' => now()->toIso8601String(),
        ];

        config([
            'licensing.author_public_key' => $keys['public_key'],
            'licensing.license_path' => $licPath,
        ]);
        $this->licenseService->clearCache();

        $rawBody = json_encode($syncPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $rawBody, hex2bin($sharedSecret));

        $response = $this->call(
            'POST',
            '/api/v1/attendance/sync',
            [],
            [],
            [],
            [
                'HTTP_X_SMARTHR_SIGNATURE' => $signature,
                'HTTP_X_SMARTHR_BRANCH' => $this->branch->code,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $rawBody
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'batch_id' => 'BATCH_TEST_001',
                'processed_records' => 2,
            ]);

        $this->assertDatabaseHas('raw_attendance_logs', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'punch_state' => 'CHECK_IN',
            'sync_batch_id' => 'BATCH_TEST_001',
        ]);
    }

    /**
     * 10. Kiểm thử API Edge Sync từ chối gói tin có chữ ký HMAC sai lệch (HTTP 403)
     */
    public function test_edge_collector_batch_sync_api_rejects_invalid_hmac(): void
    {
        $syncPayload = [
            'tenant_domain' => 'hrm.smarthr.vn',
            'branch_code' => $this->branch->code,
            'batch_id' => 'BATCH_TAMPERED',
            'punches' => [
                [
                    'user_code' => 'EMP001',
                    'punch_time' => '2026-09-07 08:00:15',
                    'punch_state' => 'CHECK_IN',
                ],
            ],
            'sent_at' => now()->toIso8601String(),
        ];

        $fakeSignature = 'deadbeefcafebabe0123456789abcdef0123456789abcdef0123456789abcdef';

        $response = $this->withHeaders([
            'X-SmartHR-Signature' => $fakeSignature,
            'X-SmartHR-Branch' => $this->branch->code,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/attendance/sync', $syncPayload);

        $response->assertStatus(403);
    }

    /**
     * 11. Kiểm thử màn hình giao diện quản trị GET /licensing
     */
    public function test_licensing_index_page_accessible_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/licensing');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page
            ->component('Licensing/Index')
            ->has('license')
            ->has('system_status')
            ->has('branches')
            ->has('recent_logs')
        );
    }

    /**
     * 12. Kiểm thử API Sandbox mã hóa & giải mã tức thì POST /licensing/test-crypto
     */
    public function test_crypto_sandbox_api_encrypt_and_decrypt(): void
    {
        $encResponse = $this->actingAs($this->adminUser)->postJson('/licensing/test-crypto', [
            'action' => 'encrypt',
            'data' => '001202007777',
        ]);

        $encResponse->assertStatus(200)
            ->assertJsonStructure(['status', 'ciphertext']);

        $ciphertext = $encResponse->json('ciphertext');

        $decResponse = $this->actingAs($this->adminUser)->postJson('/licensing/test-crypto', [
            'action' => 'decrypt',
            'data' => $ciphertext,
        ]);

        $decResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'plaintext' => '001202007777',
            ]);
    }

    /**
     * 13. Kiểm thử tải lên file license hợp lệ kích hoạt hệ thống thành công
     */
    public function test_upload_valid_license_content_activates_system(): void
    {
        $keys = $this->licenseService->generateKeyPair();
        $licContent = $this->licenseService->createLicense(
            domain: 'hrm.upload-test.com',
            customerName: 'Doanh Nghiệp Kích Hoạt Mới',
            expiresAt: date('Y-m-d', strtotime('+1 year')),
            privateKeyHex: $keys['private_key']
        );

        config(['licensing.author_public_key' => $keys['public_key']]);
        $this->licenseService->clearCache();

        $response = $this->actingAs($this->adminUser)->post('/licensing/upload', [
            'license_content' => $licContent,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /**
     * 14. Kiểm thử tải lên license giả mạo bị từ chối
     */
    public function test_upload_tampered_license_returns_error(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/licensing/upload', [
            'license_content' => '{"format":"SMARTHR-LIC-V1","payload":"invalid","signature":"fake"}',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
