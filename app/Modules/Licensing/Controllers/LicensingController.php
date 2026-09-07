<?php

declare(strict_types=1);

namespace App\Modules\Licensing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\RawAttendanceLog;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\Licensing\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LicensingController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Màn hình Quản trị Bản quyền Doanh nghiệp & Máy Chấm Công LAN
     */
    public function index(Request $request): InertiaResponse
    {
        $tenantId = Auth::user()?->tenant_id ?? 1;

        // Thông tin bản quyền hiện tại
        $license = $this->licenseService->getActiveLicense();

        // Trạng thái mật mã hệ thống
        $currentDomain = $request->getHost();
        $dek = $this->licenseService->getSystemDek($currentDomain);

        $systemStatus = [
            'current_domain' => $currentDomain,
            'dek_active' => ($dek !== null),
            'crypto_engine' => 'AES-256-GCM + HKDF-SHA256',
            'sodium_available' => extension_loaded('sodium'),
            'openssl_gcm_available' => in_array('aes-256-gcm', openssl_get_cipher_methods()),
            'php_version' => PHP_VERSION,
            'license_file_exists' => File::exists(base_path('smart-hr.lic')) || File::exists(storage_path('license/smart-hr.lic')),
        ];

        // Danh sách chi nhánh & trạm Edge
        $branches = Branch::where('tenant_id', $tenantId)
            ->select('id', 'name', 'code', 'address', 'secret_key', 'is_active')
            ->get();

        // Nhật ký quẹt thẻ gần nhất từ Edge Daemon
        $recentLogs = RawAttendanceLog::where('tenant_id', $tenantId)
            ->with(['employee:id,user_code,full_name'])
            ->select('id', 'employee_id', 'punch_time', 'punch_state', 'device_sn', 'verification_mode', 'sync_batch_id', 'created_at')
            ->latest('id')
            ->take(10)
            ->get();

        $stats = [
            'total_raw_today' => RawAttendanceLog::where('tenant_id', $tenantId)
                ->whereDate('punch_time', today())
                ->count(),
            'total_batches_all' => RawAttendanceLog::where('tenant_id', $tenantId)
                ->distinct('sync_batch_id')
                ->count('sync_batch_id'),
        ];

        return Inertia::render('Licensing/Index', [
            'license' => $license,
            'system_status' => $systemStatus,
            'branches' => $branches,
            'recent_logs' => $recentLogs,
            'stats' => $stats,
        ]);
    }

    /**
     * Tải lên và kích hoạt tệp bản quyền mới (smart-hr.lic)
     */
    public function upload(Request $request): RedirectResponse
    {
        $content = null;

        if ($request->hasFile('license_file')) {
            $file = $request->file('license_file');
            $content = file_get_contents($file->getRealPath());
        } elseif ($request->filled('license_content')) {
            $content = (string)$request->input('license_content');
        }

        if (empty($content)) {
            return back()->with('error', 'Vui lòng chọn tệp giấy phép (.lic) hoặc dán nội dung bản quyền.');
        }

        // Xác thực tính toàn vẹn bằng chữ ký số Ed25519
        $verified = $this->licenseService->verifyLicense($content);

        if (!$verified) {
            return back()->with(
                'error',
                'KÍCH HOẠT THẤT BẠI: Tệp giấy phép không hợp lệ, bị chỉnh sửa hoặc chữ ký số Ed25519 không khớp với tác giả!'
            );
        }

        if ($verified['is_expired'] ?? false) {
            return back()->with(
                'error',
                "GIẤY PHÉP ĐÃ HẾT HẠN vào ngày {$verified['expires_at']}. Vui lòng liên hệ tác giả để gia hạn."
            );
        }

        // Lưu tệp vào storage/license/ và root
        $storageDir = storage_path('license');
        if (!File::isDirectory($storageDir)) {
            File::makeDirectory($storageDir, 0755, true);
        }

        File::put($storageDir . '/smart-hr.lic', $content);
        
        // Thử lưu vào thư mục gốc nếu có quyền ghi
        try {
            File::put(base_path('smart-hr.lic'), $content);
        } catch (\Throwable) {
            // Không chặn nếu thư mục gốc chỉ đọc
        }

        return back()->with(
            'success',
            "Kích hoạt bản quyền thành công cho [{$verified['customer_name']}]! Tên miền: {$verified['allowed_domain']} (Hạn dùng: {$verified['expires_at']})"
        );
    }

    /**
     * Sandbox thử nghiệm mã hóa & giải mã dữ liệu nhạy cảm bằng DEK
     */
    public function testCrypto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:encrypt,decrypt',
            'data' => 'required|string',
            'custom_domain' => 'nullable|string',
        ]);

        $action = $validated['action'];
        $data = $validated['data'];
        $domain = $validated['custom_domain'] ?? null;

        if ($action === 'encrypt') {
            $ciphertext = $this->licenseService->encryptCitizenId($data, $domain);
            return response()->json([
                'status' => 'success',
                'ciphertext' => $ciphertext,
                'algorithm' => 'AES-256-GCM',
                'iv_length' => '12 bytes (96-bit)',
                'tag_length' => '16 bytes (128-bit)',
            ]);
        }

        // Decrypt
        $plaintext = $this->licenseService->decryptCitizenId($data, $domain);
        if ($plaintext === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Giải mã thất bại! Khóa DEK không khớp (do sai Domain/License) hoặc chuỗi ciphertext bị lỗi.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'plaintext' => $plaintext,
        ]);
    }

    /**
     * Tải tệp cấu hình mẫu config.json cho Golang Edge Collector
     */
    public function downloadSampleConfig(Request $request): Response
    {
        $host = $request->getSchemeAndHttpHost();
        $sampleConfig = [
            'server_url' => $host,
            'device_ip' => '192.168.1.201',
            'device_port' => 4370,
            'branch_code' => 'HN_HQ',
        ];

        $json = json_encode($sampleConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="config.json"',
        ]);
    }
}
