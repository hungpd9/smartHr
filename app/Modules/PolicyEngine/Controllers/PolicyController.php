<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Models\AttendancePolicyConfig;
use App\Modules\PolicyEngine\Models\PayrollPolicyConfig;
use App\Modules\PolicyEngine\Models\ChildWelfarePolicy;
use App\Modules\PolicyEngine\Models\WelfareScheme;
use App\Modules\PolicyEngine\Models\Allowance;
use App\Modules\CoreHR\Models\Holiday;
use App\Modules\PolicyEngine\Models\PitTaxBracket;
use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\Compliance\Services\AuditTrailService;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use App\Modules\PolicyEngine\Services\PolicyVersionService;
use App\Modules\PolicyEngine\Requests\StorePolicyVersionRequest;
use App\Modules\PolicyEngine\Requests\UpdateAttendanceConfigRequest;
use App\Modules\PolicyEngine\Requests\UpdatePayrollConfigRequest;
use App\Modules\PolicyEngine\Requests\StoreChildWelfarePolicyRequest;
use App\Modules\PolicyEngine\Requests\StoreWelfareSchemeRequest;
use App\Modules\PolicyEngine\Requests\StoreAllowanceRequest;
use App\Modules\PolicyEngine\Requests\TestCalculationRequest;
use App\Enums\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PolicyController extends Controller
{
    public function __construct(
        protected PolicyResolutionService $resolutionService,
        protected PolicyVersionService $versionService,
        protected TemporalLockService $temporalLockService,
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Màn hình Tổng quan Cấu hình Quy chế & Chính sách Động (Zero-Hardcode)
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        // Lấy phiên bản được chọn hoặc mặc định là phiên bản đang có hiệu lực hôm nay
        $selectedVersionId = $request->query('version_id');
        if ($selectedVersionId) {
            $activeVersion = PolicyVersion::where('tenant_id', $tenantId)
                ->with(['attendanceConfig', 'payrollConfig', 'childWelfarePolicies', 'welfareSchemes', 'pitTaxBrackets'])
                ->find($selectedVersionId);
        } else {
            $activeVersion = $this->resolutionService->resolveForDate($tenantId, Carbon::today());
        }

        // Nếu chưa có phiên bản nào, lấy phiên bản đầu tiên
        if (!$activeVersion) {
            $activeVersion = PolicyVersion::where('tenant_id', $tenantId)
                ->with(['attendanceConfig', 'payrollConfig', 'childWelfarePolicies', 'welfareSchemes', 'pitTaxBrackets'])
                ->latest('effective_from')
                ->first();
        }

        // Nếu activeVersion thiếu payrollConfig, tự động tạo mới
        if ($activeVersion && !$activeVersion->payrollConfig) {
            $this->versionService->initDefaultPayrollConfig($activeVersion);
            $activeVersion->load('payrollConfig');
        }

        // Danh sách tất cả phiên bản để xem lịch sử
        $versions = PolicyVersion::where('tenant_id', $tenantId)
            ->withCount(['childWelfarePolicies', 'welfareSchemes', 'pitTaxBrackets'])
            ->orderByDesc('effective_from')
            ->get();

        // Danh mục ngày nghỉ lễ tết năm 2026
        $holidays = Holiday::where('tenant_id', $tenantId)
            ->where('year', 2026)
            ->orderBy('holiday_date')
            ->get();

        // Danh mục phụ cấp doanh nghiệp
        $allowances = Allowance::where('tenant_id', $tenantId)->orderBy('code')->get();

        // Danh sách chế độ phúc lợi độc lập theo tenant
        $allWelfareSchemes = WelfareScheme::where('tenant_id', $tenantId)->orderBy('name')->get();

        // Quyền hạn phân tầng
        $canEditPolicy = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true);
        $canEditPayroll = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true);
        $canTest = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT, UserRole::EMPLOYEE], true);

        $isLocked = $activeVersion ? $this->temporalLockService->isPolicyVersionLocked($activeVersion) : false;

        return Inertia::render('Policy/Index', [
            'activeVersion' => $activeVersion,
            'versions' => $versions,
            'holidays' => $holidays,
            'allowances' => $allowances,
            'allWelfareSchemes' => $allWelfareSchemes,
            'isLocked' => $isLocked,
            'canEdit' => $canEditPolicy && !$isLocked,
            'canEditPayroll' => $canEditPayroll && !$isLocked,
            'canTest' => $canTest,
            'currentDate' => Carbon::today()->format('d/m/Y'),
        ]);
    }

    /**
     * Tạo phiên bản quy chế mới (Effective Dating)
     */
    public function storeVersion(StorePolicyVersionRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền tạo phiên bản quy chế.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $cloneFrom = $request->filled('clone_from_version_id') ? (int) $request->input('clone_from_version_id') : null;

        $newVersion = $this->versionService->createVersion($tenantId, $request->validated(), $cloneFrom);

        $this->auditTrailService->log(
            $tenantId,
            'CREATE_POLICY_VERSION',
            $newVersion,
            null,
            $newVersion->toArray()
        );

        return redirect()->route('policies.index', ['version_id' => $newVersion->id])
            ->with('success', 'Đã khởi tạo thành công phiên bản quy chế mới.');
    }

    /**
     * Cập nhật cấu hình chấm công & nấc phạt
     */
    public function updateAttendanceConfig(UpdateAttendanceConfigRequest $request, int $versionId): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền sửa đổi quy chế chấm công.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $version = PolicyVersion::where('tenant_id', $tenantId)->findOrFail($versionId);

        // Bảo vệ toàn vẹn lịch sử: Chặn sửa đổi nếu version đã dùng trong kỳ chốt công/lương
        $this->temporalLockService->assertPolicyNotLocked($version);

        $config = $version->attendanceConfig()->firstOrFail();
        $oldValues = $config->toArray();

        $this->versionService->updateAttendanceConfig($config, $request->validated());

        $this->auditTrailService->log(
            $tenantId,
            'UPDATE_ATTENDANCE_CONFIG',
            $config,
            $oldValues,
            $config->fresh()->toArray()
        );

        return redirect()->route('policies.index', ['version_id' => $versionId])
            ->with('success', 'Đã lưu cập nhật quy chế chấm công & nấc phạt.');
    }

    /**
     * Cập nhật cấu hình lương, thuế & bảo hiểm (Kế toán & HR Admin)
     */
    public function updatePayrollConfig(UpdatePayrollConfigRequest $request, int $versionId): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền sửa đổi cấu hình lương & bảo hiểm.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $version = PolicyVersion::where('tenant_id', $tenantId)->findOrFail($versionId);

        // Bảo vệ toàn vẹn lịch sử: Chặn sửa đổi nếu version đã dùng trong kỳ chốt công/lương
        $this->temporalLockService->assertPolicyNotLocked($version);

        $config = $version->payrollConfig ?? $this->versionService->initDefaultPayrollConfig($version);
        $oldValues = $config->toArray();

        $this->versionService->updatePayrollConfig($config, $request->validated());

        $this->auditTrailService->log(
            $tenantId,
            'UPDATE_PAYROLL_CONFIG',
            $config,
            $oldValues,
            $config->fresh()->toArray()
        );

        return redirect()->route('policies.index', ['version_id' => $versionId])
            ->with('success', 'Đã lưu cấu hình tiền lương, thuế TNCN & mức trích nộp bảo hiểm.');
    }

    // ==========================================
    // PHÚC LỢI CON NHỎ (CHILD WELFARE POLICIES)
    // ==========================================

    /**
     * Thêm chế độ phúc lợi con nhỏ mới
     */
    public function storeChildWelfarePolicy(StoreChildWelfarePolicyRequest $request, int $versionId): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền thêm chính sách phúc lợi.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $version = PolicyVersion::where('tenant_id', $tenantId)->findOrFail($versionId);
        $this->temporalLockService->assertPolicyNotLocked($version);

        $data = $request->validated();
        $data['visibility'] = $data['visibility'] ?? 'VISIBLE';
        $data['is_active'] = ($data['visibility'] === 'VISIBLE');

        $policy = $version->childWelfarePolicies()->create($data);
        $this->resolutionService->clearPolicyCache($tenantId);

        $this->auditTrailService->log(
            $tenantId,
            'CREATE_CHILD_WELFARE',
            $policy,
            null,
            $policy->toArray()
        );

        return redirect()->route('policies.index', ['version_id' => $versionId])
            ->with('success', "Đã bổ sung chính sách phúc lợi '{$policy->name}' thành công.");
    }

    /**
     * Cập nhật chính sách phúc lợi con nhỏ
     */
    public function updateChildWelfarePolicy(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền sửa đổi chính sách phúc lợi.');
        }

        $policy = ChildWelfarePolicy::findOrFail($id);
        $version = $policy->policyVersion;
        $this->temporalLockService->assertPolicyNotLocked($version);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'min_age_months' => ['required', 'integer', 'min:0'],
            'max_age_months' => ['required', 'integer', 'gt:min_age_months'],
            'daily_exemption_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'pay_type' => ['required', 'in:PAID,UNPAID,PARTIAL'],
            'prohibit_night_shift' => ['boolean'],
            'prohibit_overtime' => ['boolean'],
            'visibility' => ['nullable', 'string', 'in:VISIBLE,HIDDEN'],
        ]);

        $validated['is_active'] = (($validated['visibility'] ?? $policy->visibility) === 'VISIBLE');
        $oldValues = $policy->toArray();
        $policy->update($validated);

        $tenantId = (int) ($version->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);

        $this->auditTrailService->log(
            $tenantId,
            'UPDATE_CHILD_WELFARE',
            $policy,
            $oldValues,
            $policy->fresh()->toArray()
        );

        return back()->with('success', "Đã cập nhật chính sách phúc lợi '{$policy->name}' thành công.");
    }

    /**
     * Xóa chính sách phúc lợi con nhỏ
     */
    public function destroyChildWelfarePolicy(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền xóa chính sách phúc lợi.');
        }

        $policy = ChildWelfarePolicy::findOrFail($id);
        if ($policy->is_statutory) {
            return back()->with('error', 'Không thể xóa chính sách luật định bắt buộc (con dưới 12 tháng tuổi).');
        }

        $version = $policy->policyVersion;
        $this->temporalLockService->assertPolicyNotLocked($version);

        $tenantId = (int) ($version->tenant_id ?? 1);
        $oldValues = $policy->toArray();
        $policy->delete();

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'DELETE_CHILD_WELFARE', $policy, $oldValues, null);

        return back()->with('success', 'Đã xóa chính sách phúc lợi thành công.');
    }

    /**
     * Bật / tắt hiển thị (VISIBLE / HIDDEN) chính sách phúc lợi con nhỏ
     */
    public function toggleChildWelfarePolicy(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền sửa đổi trạng thái chính sách.');
        }

        $policy = ChildWelfarePolicy::findOrFail($id);
        $version = $policy->policyVersion;
        $this->temporalLockService->assertPolicyNotLocked($version);

        $newVisibility = ($policy->visibility === 'VISIBLE') ? 'HIDDEN' : 'VISIBLE';
        $policy->update([
            'visibility' => $newVisibility,
            'is_active' => ($newVisibility === 'VISIBLE'),
        ]);

        $tenantId = (int) ($version->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', "Đã chuyển trạng thái chính sách sang: {$newVisibility}.");
    }

    // ==========================================
    // BIỂU THUẾ TNCN ĐỘNG (PIT TAX BRACKETS)
    // ==========================================

    /**
     * Thêm mới bậc thuế TNCN vào phiên bản quy chế
     */
    public function storePitTaxBracket(Request $request, int $versionId): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền sửa đổi biểu thuế.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $version = PolicyVersion::where('tenant_id', $tenantId)->findOrFail($versionId);
        $this->temporalLockService->assertPolicyNotLocked($version);

        $validated = $request->validate([
            'tier' => ['required', 'integer', 'min:1'],
            'min_income' => ['required', 'numeric', 'min:0'],
            'max_income' => ['nullable', 'numeric'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'quick_subtraction' => ['required', 'numeric', 'min:0'],
        ]);

        $bracket = $version->pitTaxBrackets()->create($validated);
        $this->resolutionService->clearPolicyCache($tenantId);

        $this->auditTrailService->log(
            $tenantId,
            'CREATE_PIT_BRACKET',
            $bracket,
            null,
            $bracket->toArray()
        );

        return back()->with('success', "Đã thêm mới Bậc thuế {$bracket->tier} ({$bracket->tax_rate}%) thành công.");
    }

    /**
     * Cập nhật bậc thuế TNCN
     */
    public function updatePitTaxBracket(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền sửa đổi biểu thuế.');
        }

        $bracket = PitTaxBracket::findOrFail($id);
        $version = $bracket->policyVersion;
        $this->temporalLockService->assertPolicyNotLocked($version);

        $validated = $request->validate([
            'tier' => ['required', 'integer', 'min:1'],
            'min_income' => ['required', 'numeric', 'min:0'],
            'max_income' => ['nullable', 'numeric'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'quick_subtraction' => ['required', 'numeric', 'min:0'],
        ]);

        $oldValues = $bracket->toArray();
        $bracket->update($validated);

        $tenantId = (int) ($version->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);

        $this->auditTrailService->log(
            $tenantId,
            'UPDATE_PIT_BRACKET',
            $bracket,
            $oldValues,
            $bracket->fresh()->toArray()
        );

        return back()->with('success', "Đã cập nhật Bậc thuế {$bracket->tier} thành công.");
    }

    /**
     * Xóa bậc thuế TNCN
     */
    public function destroyPitTaxBracket(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền xóa bậc thuế.');
        }

        $bracket = PitTaxBracket::findOrFail($id);
        $version = $bracket->policyVersion;
        $this->temporalLockService->assertPolicyNotLocked($version);

        $tenantId = (int) ($version->tenant_id ?? 1);
        $oldValues = $bracket->toArray();
        $bracket->delete();

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'DELETE_PIT_BRACKET', $bracket, $oldValues, null);

        return back()->with('success', 'Đã xóa bậc thuế thành công.');
    }

    /**
     * Khôi phục Biểu thuế 5 bậc chuẩn theo quy định năm 2026
     */
    public function resetDefaultPitTaxBrackets(int $versionId): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền khôi phục biểu thuế.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $version = PolicyVersion::where('tenant_id', $tenantId)->findOrFail($versionId);
        $this->temporalLockService->assertPolicyNotLocked($version);

        $version->pitTaxBrackets()->delete();

        $defaultBrackets = [
            ['tier' => 1, 'min_income' => 0.00, 'max_income' => 10000000.00, 'tax_rate' => 5.00, 'quick_subtraction' => 0.00],
            ['tier' => 2, 'min_income' => 10000000.01, 'max_income' => 30000000.00, 'tax_rate' => 10.00, 'quick_subtraction' => 500000.00],
            ['tier' => 3, 'min_income' => 30000000.01, 'max_income' => 60000000.00, 'tax_rate' => 20.00, 'quick_subtraction' => 3500000.00],
            ['tier' => 4, 'min_income' => 60000000.01, 'max_income' => 100000000.00, 'tax_rate' => 30.00, 'quick_subtraction' => 9500000.00],
            ['tier' => 5, 'min_income' => 100000000.01, 'max_income' => null, 'tax_rate' => 35.00, 'quick_subtraction' => 14500000.00],
        ];

        foreach ($defaultBrackets as $bracket) {
            $version->pitTaxBrackets()->create($bracket);
        }

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'RESET_PIT_BRACKETS', $version, null, ['status' => 'RESET_5_TIERS_2026']);

        return back()->with('success', 'Đã khôi phục Biểu thuế TNCN 5 bậc chuẩn năm 2026 thành công!');
    }

    // ==========================================
    // CHẾ ĐỘ PHÚC LỢI ĐA NĂNG (WELFARE SCHEMES)
    // ==========================================

    /**
     * Thêm mới chế độ phúc lợi doanh nghiệp đa năng
     */
    public function storeWelfareScheme(StoreWelfareSchemeRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền tạo chế độ phúc lợi.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        $scheme = WelfareScheme::create($data);
        $this->resolutionService->clearPolicyCache($tenantId);

        $this->auditTrailService->log($tenantId, 'CREATE_WELFARE_SCHEME', $scheme, null, $scheme->toArray());

        return back()->with('success', 'Đã tạo mới chế độ phúc lợi doanh nghiệp thành công.');
    }

    /**
     * Cập nhật chế độ phúc lợi doanh nghiệp đa năng
     */
    public function updateWelfareScheme(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền sửa chế độ phúc lợi.');
        }

        $scheme = WelfareScheme::findOrFail($id);
        $tenantId = (int) ($scheme->tenant_id ?? 1);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string'],
            'daily_exemption_minutes' => ['nullable', 'integer', 'min:0'],
            'pay_type' => ['nullable', 'string'],
            'prohibit_night_shift' => ['boolean'],
            'prohibit_overtime' => ['boolean'],
            'monthly_allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'one_time_bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'extra_leave_days' => ['nullable', 'numeric', 'min:0'],
            'is_tax_exempt' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $oldValues = $scheme->toArray();
        $scheme->update($validated);

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'UPDATE_WELFARE_SCHEME', $scheme, $oldValues, $scheme->fresh()->toArray());

        return back()->with('success', "Cập nhật chế độ phúc lợi '{$scheme->name}' thành công.");
    }

    /**
     * Bật / tắt hiển thị (VISIBLE / HIDDEN) chế độ phúc lợi doanh nghiệp
     */
    public function toggleWelfareSchemeVisibility(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền thay đổi trạng thái chế độ.');
        }

        $scheme = WelfareScheme::findOrFail($id);
        $newVisibility = ($scheme->visibility === 'VISIBLE') ? 'HIDDEN' : 'VISIBLE';
        $scheme->update(['visibility' => $newVisibility]);

        $tenantId = (int) ($scheme->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', "Đã chuyển chế độ '{$scheme->name}' sang trạng thái: {$newVisibility}.");
    }

    /**
     * Xóa chế độ phúc lợi doanh nghiệp
     */
    public function destroyWelfareScheme(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền xóa chế độ phúc lợi.');
        }

        $scheme = WelfareScheme::findOrFail($id);
        if ($scheme->is_statutory) {
            return back()->with('error', 'Không được phép xóa chế độ luật định bắt buộc.');
        }

        $tenantId = (int) ($scheme->tenant_id ?? 1);
        $oldValues = $scheme->toArray();
        $scheme->delete();

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'DELETE_WELFARE_SCHEME', $scheme, $oldValues, null);

        return back()->with('success', 'Đã xóa chế độ phúc lợi thành công.');
    }

    // ==========================================
    // DANH MỤC PHỤ CẤP DOANH NGHIỆP (ALLOWANCES)
    // ==========================================

    /**
     * Thêm mới khoản phụ cấp doanh nghiệp
     */
    public function storeAllowance(StoreAllowanceRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền tạo danh mục phụ cấp.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        $allowance = Allowance::create($data);
        $this->resolutionService->clearPolicyCache($tenantId);

        $this->auditTrailService->log($tenantId, 'CREATE_ALLOWANCE', $allowance, null, $allowance->toArray());

        return back()->with('success', 'Đã lưu danh mục phụ cấp mới thành công.');
    }

    /**
     * Cập nhật khoản phụ cấp doanh nghiệp (kèm trần miễn thuế TNCN)
     */
    public function updateAllowance(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền sửa đổi phụ cấp.');
        }

        $allowance = Allowance::findOrFail($id);
        $tenantId = (int) ($allowance->tenant_id ?? 1);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'apply_type' => ['required', 'in:MONTHLY_FIXED,PER_DAY,PERCENTAGE'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'tax_exemption_limit' => ['nullable', 'numeric', 'min:0'],
            'is_insurance_taxable' => ['boolean'],
            'is_pit_taxable' => ['boolean'],
            'is_included_in_ot_wage' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $oldValues = $allowance->toArray();
        $allowance->update($validated);

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'UPDATE_ALLOWANCE', $allowance, $oldValues, $allowance->fresh()->toArray());

        return back()->with('success', "Cập nhật phụ cấp '{$allowance->name}' thành công.");
    }

    /**
     * Bật / tắt hiển thị khoản phụ cấp
     */
    public function toggleAllowanceVisibility(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền thay đổi trạng thái phụ cấp.');
        }

        $allowance = Allowance::findOrFail($id);
        $newVisibility = ($allowance->visibility === 'VISIBLE') ? 'HIDDEN' : 'VISIBLE';
        $allowance->update(['visibility' => $newVisibility]);

        $tenantId = (int) ($allowance->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', "Đã cập nhật hiển thị phụ cấp: {$newVisibility}.");
    }

    /**
     * Xóa khoản phụ cấp
     */
    public function destroyAllowance(int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền xóa phụ cấp.');
        }

        $allowance = Allowance::findOrFail($id);
        $tenantId = (int) ($allowance->tenant_id ?? 1);
        $oldValues = $allowance->toArray();
        $allowance->delete();

        $this->resolutionService->clearPolicyCache($tenantId);
        $this->auditTrailService->log($tenantId, 'DELETE_ALLOWANCE', $allowance, $oldValues, null);

        return back()->with('success', 'Đã xóa khoản phụ cấp thành công.');
    }

    /**
     * Sandbox Thử nghiệm Tính toán: Phạt đi muộn hoặc Thuế TNCN
     */
    public function testCalculation(TestCalculationRequest $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $targetDate = $request->filled('target_date') ? Carbon::parse($request->input('target_date')) : Carbon::today();

        if ($request->input('type') === 'LATE_PENALTY') {
            $lateMinutes = (int) $request->input('late_minutes', 0);
            $violationCount = (int) $request->input('violation_count', 1);

            $result = $this->resolutionService->calculateLatePenalty($tenantId, $targetDate, $lateMinutes, $violationCount);
            return response()->json(['success' => true, 'result' => $result]);
        }

        if ($request->input('type') === 'PIT_TAX') {
            $taxableIncome = (float) $request->input('taxable_income', 0);

            $result = $this->resolutionService->calculatePitTax($tenantId, $targetDate, $taxableIncome);
            return response()->json(['success' => true, 'result' => $result]);
        }

        return response()->json(['success' => false, 'message' => 'Loại tính toán không hợp lệ.'], 422);
    }
}
