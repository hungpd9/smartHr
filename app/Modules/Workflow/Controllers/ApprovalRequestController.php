<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\LeavePolicy\Enums\LeaveType;
use App\Modules\LeavePolicy\Models\LeaveBalance;
use App\Modules\LeavePolicy\Services\LeaveBalanceService;
use App\Modules\Workflow\Enums\RequestStatus;
use App\Modules\Workflow\Enums\RequestType;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Requests\ApproveStepRequest;
use App\Modules\Workflow\Requests\CreateApprovalRequest;
use App\Modules\Workflow\Requests\RejectStepRequest;
use App\Modules\Workflow\Services\WorkflowExecutionService;
use App\Modules\Workflow\Services\WorkflowRoutingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalRequestController extends Controller
{
    public function __construct(
        protected WorkflowExecutionService $workflowExecutionService,
        protected WorkflowRoutingService $workflowRoutingService,
        protected LeaveBalanceService $leaveBalanceService
    ) {}

    /**
     * Màn hình chính quản trị luồng đơn từ: Đơn cần duyệt, Đơn của tôi, Cấu hình quy trình
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $currentEmployee = Employee::where('tenant_id', $tenantId)
            ->where('email', $user->email)
            ->first() ?? Employee::where('tenant_id', $tenantId)->first();

        $activeTab = $request->query('tab', 'to_approve');
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');

        // Đảm bảo có sẵn definitions mặc định
        $definitionsCount = WorkflowDefinition::where('tenant_id', $tenantId)->count();
        if ($definitionsCount === 0) {
            $this->workflowRoutingService->seedDefaultDefinitions($tenantId);
        }

        // 1. Danh sách đơn tôi cần duyệt (Cấp duyệt hiện tại khớp với currentEmployee hoặc User có role Admin)
        $isAdmin = in_array($user->role, ['SUPER_ADMIN', 'HR_ADMIN', 'ADMIN'], true);

        $toApproveQuery = ApprovalRequest::where('tenant_id', $tenantId)
            ->where('status', RequestStatus::PENDING->value)
            ->with(['employee.children', 'histories.approver']);

        if (!$isAdmin && $currentEmployee) {
            $toApproveQuery->where(function ($q) use ($currentEmployee) {
                $q->whereJsonContains('snapshot_approvers', ['approver_id' => $currentEmployee->id]);
            });
        }

        if ($typeFilter) {
            $toApproveQuery->where('request_type', $typeFilter);
        }

        $requestsToApprove = $toApproveQuery->orderByDesc('id')->get()
            ->filter(function ($req) use ($currentEmployee, $isAdmin) {
                if ($isAdmin) {
                    return true;
                }
                $snapshot = $req->snapshot_approvers ?? [];
                $step = $req->current_step - 1;
                return isset($snapshot[$step]) && ($snapshot[$step]['approver_id'] ?? null) === $currentEmployee?->id;
            })
            ->values();

        // 2. Danh sách đơn của tôi (My Requests)
        $myRequestsQuery = ApprovalRequest::where('tenant_id', $tenantId)
            ->with(['employee.children', 'histories.approver']);

        if ($currentEmployee) {
            $myRequestsQuery->where('employee_id', $currentEmployee->id);
        }

        if ($statusFilter) {
            $myRequestsQuery->where('status', $statusFilter);
        }

        if ($typeFilter) {
            $myRequestsQuery->where('request_type', $typeFilter);
        }

        $myRequests = $myRequestsQuery->orderByDesc('id')->paginate(15)->withQueryString();

        // 3. Số dư phép năm hiện tại của nhân viên để hiển thị trên UI
        $currentYear = (int) Carbon::today()->year;
        $leaveBalance = null;
        if ($currentEmployee) {
            $leaveBalance = LeaveBalance::where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->where('year', $currentYear)
                ->where('leave_type', LeaveType::ANNUAL->value)
                ->first();

            if (!$leaveBalance) {
                $leaveBalance = $this->leaveBalanceService->initializeOrUpdateAnnualBalance($currentEmployee, $currentYear);
            }
        }

        // 4. Danh sách tất cả nhân viên (phục vụ chọn người hoặc nộp hộ nếu là HR)
        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->select('id', 'full_name', 'user_code', 'email', 'gender')
            ->orderBy('full_name')
            ->get();

        // 5. Cấu hình quy trình hiện tại
        $workflowConfigs = WorkflowDefinition::where('tenant_id', $tenantId)
            ->orderBy('request_type')
            ->get();

        return Inertia::render('Workflow/Index', [
            'requestsToApprove' => $requestsToApprove,
            'myRequests' => $myRequests,
            'currentEmployee' => $currentEmployee,
            'leaveBalance' => $leaveBalance,
            'employees' => $employees,
            'workflowConfigs' => $workflowConfigs,
            'filters' => [
                'tab' => $activeTab,
                'status' => $statusFilter,
                'type' => $typeFilter,
            ],
            'requestTypes' => array_map(fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ], RequestType::cases()),
        ]);
    }

    /**
     * Nộp đơn mới
     */
    public function store(CreateApprovalRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $employeeId = $request->input('employee_id');
        if (!$employeeId) {
            $currentEmployee = Employee::where('tenant_id', $tenantId)
                ->where('email', $user->email)
                ->first() ?? Employee::where('tenant_id', $tenantId)->first();
            $employeeId = $currentEmployee?->id;
        }

        if (!$employeeId) {
            return redirect()->back()->with('error', 'Không xác định được nhân sự nộp đơn.');
        }

        $this->workflowExecutionService->createRequest(
            $tenantId,
            (int) $employeeId,
            $request->input('request_type'),
            $request->input('form_payload', []),
            (bool) $request->input('is_voluntary_consent', false),
            $request->input('voluntary_agreement_file'),
            $request->input('voluntary_agreement_url')
        );

        return redirect()->back()->with('success', 'Đã nộp đơn thành công. Đơn đã được chuyển đến cấp duyệt thẩm quyền.');
    }

    /**
     * Phê duyệt bước hiện tại của đơn
     */
    public function approve(ApproveStepRequest $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $approvalRequest = ApprovalRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $currentEmployee = $user->employee
            ?? Employee::where('tenant_id', $tenantId)->where('email', $user->email)->first()
            ?? Employee::where('tenant_id', $tenantId)->first();

        if (!$currentEmployee) {
            return redirect()->back()->with('error', 'Không tìm thấy hồ sơ nhân sự của người duyệt.');
        }

        $this->workflowExecutionService->approveStep(
            $approvalRequest,
            $currentEmployee,
            $request->input('comment', ''),
            (bool) $request->input('verified_child_under_12m_consent', false),
            $user
        );

        return redirect()->back()->with('success', 'Đã phê duyệt thành công.');
    }

    /**
     * Từ chối đơn
     */
    public function reject(RejectStepRequest $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $approvalRequest = ApprovalRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $currentEmployee = $user->employee
            ?? Employee::where('tenant_id', $tenantId)->where('email', $user->email)->first()
            ?? Employee::where('tenant_id', $tenantId)->first();

        if (!$currentEmployee) {
            return redirect()->back()->with('error', 'Không tìm thấy hồ sơ nhân sự của người duyệt.');
        }

        $this->workflowExecutionService->rejectStep(
            $approvalRequest,
            $currentEmployee,
            $request->input('comment', ''),
            $user
        );

        return redirect()->back()->with('success', 'Đã từ chối đơn yêu cầu.');
    }

    /**
     * Hủy đơn do chính nhân sự nộp
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $approvalRequest = ApprovalRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $currentEmployee = Employee::where('tenant_id', $tenantId)
            ->where('email', $user->email)
            ->first() ?? Employee::where('tenant_id', $tenantId)->first();

        if (!$currentEmployee) {
            return redirect()->back()->with('error', 'Không tìm thấy hồ sơ nhân sự.');
        }

        $this->workflowExecutionService->cancelRequest($approvalRequest, $currentEmployee);

        return redirect()->back()->with('success', 'Đã hủy đơn thành công.');
    }

    /**
     * API Preview chuỗi người duyệt động khi người dùng chọn loại đơn trên Form
     */
    public function previewChain(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $employeeId = (int) ($request->query('employee_id') ?? 0);
        $requestType = (string) $request->query('request_type', 'LEAVE');
        $payload = $request->input('payload', []);

        $employee = Employee::where('tenant_id', $tenantId)->find($employeeId)
            ?? Employee::where('tenant_id', $tenantId)->where('email', $user->email)->first()
            ?? Employee::where('tenant_id', $tenantId)->first();

        if (!$employee) {
            return response()->json(['chain' => []]);
        }

        $chain = $this->workflowRoutingService->resolveApprovalChain(
            $tenantId,
            $employee,
            $requestType,
            $payload
        );

        return response()->json(['chain' => $chain]);
    }
}
