<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use App\Modules\Rostering\Models\ShiftSwapRequest;
use App\Modules\Rostering\Services\ShiftSwapService;
use App\Modules\Rostering\Requests\CreateSwapRequest;

class ShiftSwapController extends Controller
{
    public function __construct(
        protected ShiftSwapService $swapService
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $requests = ShiftSwapRequest::whereHas('requester', fn($q) => $q->where('tenant_id', $tenantId))
            ->with([
                'requester',
                'targetEmployee',
                'requesterRoster.shift',
                'targetRoster.shift',
            ])
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Rostering/Swaps/Index', [
            'swapRequests' => $requests,
        ]);
    }

    public function store(CreateSwapRequest $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return redirect()->back()->with('error', 'Tài khoản của bạn chưa liên kết với hồ sơ nhân viên.');
        }

        $data = $request->validated();

        $this->swapService->createSwapRequest(
            $employee->id,
            (int)$data['requester_roster_id'],
            (int)$data['target_employee_id'],
            isset($data['target_roster_id']) ? (int)$data['target_roster_id'] : null,
            $data['swap_type'],
            $data['reason'] ?? null
        );

        return redirect()->back()->with('success', 'Đã gửi yêu cầu đổi ca thành công, đang chờ Quản lý phê duyệt.');
    }

    public function approve(Request $request, ShiftSwapRequest $swapRequest): RedirectResponse
    {
        $user = $request->user();
        $this->swapService->approveSwap($swapRequest->id, $user->employee?->id);

        return redirect()->back()->with('success', 'Đã phê duyệt yêu cầu hoán đổi ca làm việc thành công.');
    }

    public function reject(Request $request, ShiftSwapRequest $swapRequest): RedirectResponse
    {
        $reason = $request->input('reason', 'Quản lý không phê duyệt yêu cầu đổi ca này.');
        $this->swapService->rejectSwap($swapRequest->id, $reason);

        return redirect()->back()->with('success', 'Đã từ chối yêu cầu đổi ca.');
    }
}
