<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;
use App\Modules\Rostering\Services\RosterService;
use App\Modules\Rostering\Solvers\AutoRosteringSolverService;
use App\Modules\Rostering\Requests\AssignRosterRequest;
use App\Modules\Rostering\Requests\AutoGenerateRosterRequest;

use Illuminate\Http\JsonResponse;

class RosterController extends Controller
{
    public function __construct(
        protected RosterService $rosterService,
        protected AutoRosteringSolverService $solverService
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        // Mặc định xem theo tuần hiện tại (Thứ 2 đến Chủ nhật)
        $startDate = $request->query('start_date', Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString());
        $departmentId = $request->query('department_id') ? (int)$request->query('department_id') : null;

        $data = $this->rosterService->getRosterMatrix($tenantId, $startDate, $endDate, $departmentId);

        return Inertia::render('Rostering/Index', $data);
    }

    public function assign(AssignRosterRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $employeeId = (int)$request->validated('employee_id');
        $shiftId = $request->validated('shift_id') ? (int)$request->validated('shift_id') : null;
        $date = (string)$request->validated('roster_date');
        $force = (bool)$request->validated('force', false);

        $this->rosterService->assignShift($tenantId, $employeeId, $date, $shiftId, $force);

        return redirect()->back()->with('success', 'Đã cập nhật ca làm việc.');
    }

    public function autoGenerate(AutoGenerateRosterRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        $result = $this->solverService->solve(
            $tenantId,
            $data['start_date'],
            $data['end_date'],
            $data['strategy'],
            $data['shift_ids'],
            $data['employee_ids'] ?? null,
            $data['preferences'] ?? [],
            false
        );

        $message = sprintf(
            'Phân ca tự động thành công: Đã xếp %d ca làm việc cho %d nhân viên (%d ca đêm, %d ngày nghỉ, ngăn chặn %d vi phạm BLLĐ). Ước tính phụ cấp ca đêm: %s đ.',
            $result['total_shifts_scheduled'],
            $result['employees_count'],
            $result['total_night_shifts'],
            $result['total_rest_days'],
            $result['violations_prevented'],
            number_format($result['estimated_night_allowance'], 0, ',', '.')
        );

        return redirect()->back()->with('success', $message);
    }

    /**
     * Mô phỏng phân ca tự động không ghi vào CSDL (phục vụ bảng đối soát & minh bạch chi phí)
     */
    public function simulateAutoGenerate(AutoGenerateRosterRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        $result = $this->solverService->solve(
            $tenantId,
            $data['start_date'],
            $data['end_date'],
            $data['strategy'],
            $data['shift_ids'],
            $data['employee_ids'] ?? null,
            $data['preferences'] ?? [],
            true
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'department_id' => 'nullable|integer',
        ]);

        $tenantId = $request->user()->tenant_id;
        $count = $this->rosterService->publishRosters(
            $tenantId,
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('department_id') ? (int)$request->input('department_id') : null
        );

        return redirect()->back()->with('success', "Đã công bố thành công {$count} lịch phân ca cho nhân viên.");
    }
}
