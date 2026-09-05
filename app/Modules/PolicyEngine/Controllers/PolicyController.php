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
        protected PolicyVersionService $versionService
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

        return Inertia::render('Policy/Index', [
            'activeVersion' => $activeVersion,
            'versions' => $versions,
            'holidays' => $holidays,
            'allowances' => $allowances,
            'allWelfareSchemes' => $allWelfareSchemes,
            'canEdit' => $canEditPolicy,
            'canEditPayroll' => $canEditPayroll,
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
        $config = $version->attendanceConfig()->firstOrFail();

        $this->versionService->updateAttendanceConfig($config, $request->validated());

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
        $config = $version->payrollConfig ?? $this->versionService->initDefaultPayrollConfig($version);

        $this->versionService->updatePayrollConfig($config, $request->validated());

        return redirect()->route('policies.index', ['version_id' => $versionId])
            ->with('success', 'Đã lưu cấu hình tiền lương, thuế TNCN & mức trích nộp bảo hiểm.');
    }

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

        $data = $request->validated();
        $data['visibility'] = $data['visibility'] ?? 'VISIBLE';
        $data['is_active'] = ($data['visibility'] === 'VISIBLE');

        $version->childWelfarePolicies()->create($data);
        $this->resolutionService->clearPolicyCache($tenantId);

        return redirect()->route('policies.index', ['version_id' => $versionId])
            ->with('success', 'Đã bổ sung chính sách phúc lợi con nhỏ thành công.');
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
        $newVisibility = ($policy->visibility === 'VISIBLE') ? 'HIDDEN' : 'VISIBLE';
        $policy->update([
            'visibility' => $newVisibility,
            'is_active' => ($newVisibility === 'VISIBLE'),
        ]);

        $tenantId = (int) ($policy->policyVersion->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', "Đã chuyển trạng thái chính sách sang: {$newVisibility}.");
    }

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

        WelfareScheme::create($data);
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', 'Đã tạo mới chế độ phúc lợi doanh nghiệp thành công.');
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
        $scheme->delete();
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', 'Đã xóa chế độ phúc lợi.');
    }

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

        Allowance::create($data);
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', 'Đã lưu danh mục phụ cấp mới thành công.');
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
        $allowance->delete();
        $this->resolutionService->clearPolicyCache($tenantId);

        return back()->with('success', 'Đã xóa khoản phụ cấp.');
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
