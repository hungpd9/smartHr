<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Compliance\Services\AuditTrailService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PositionController extends Controller
{
    public function __construct(
        protected AuditTrailService $auditTrailService
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 1);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus_one_time' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['is_active'] = true;

        $position = Position::create($validated);

        $this->auditTrailService->log(
            $tenantId,
            'CREATE',
            $position,
            null,
            $position->toArray()
        );

        return redirect()->back()->with('success', "Thêm mới chức danh '{$position->name}' thành công!");
    }

    public function update(int $id, Request $request): RedirectResponse
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 1);
        $position = Position::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus_one_time' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldValues = $position->toArray();
        $position->update($validated);

        $this->auditTrailService->log(
            $tenantId,
            'UPDATE',
            $position,
            $oldValues,
            $position->fresh()->toArray()
        );

        return redirect()->back()->with('success', "Cập nhật chức danh '{$position->name}' thành công!");
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 1);
        $position = Position::where('tenant_id', $tenantId)->findOrFail($id);

        // Kiểm tra xem có nhân sự đang giữ chức vụ này không
        $isAssigned = EmployeeCareerTimeline::where('position_id', $position->id)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', Carbon::today());
            })
            ->exists();

        if ($isAssigned) {
            throw ValidationException::withMessages([
                'position' => [
                    "Không thể xóa chức danh '{$position->name}' vì đang có nhân sự đảm nhiệm. " .
                    "Vui lòng điều chuyển chức vụ của nhân sự trước hoặc tắt kích hoạt chức danh này."
                ],
            ]);
        }

        $oldValues = $position->toArray();
        $position->delete();

        $this->auditTrailService->log(
            $tenantId,
            'DELETE',
            $position,
            $oldValues,
            null
        );

        return redirect()->back()->with('success', "Đã xóa chức danh '{$position->name}' thành công!");
    }
}
