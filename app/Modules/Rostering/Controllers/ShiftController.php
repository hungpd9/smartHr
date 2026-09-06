<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Requests\SaveShiftRequest;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $shifts = Shift::where('tenant_id', $tenantId)
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Rostering/Shifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    public function store(SaveShiftRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        // Tự động phát hiện ca đêm nếu có giờ nằm trong 22:00 - 06:00
        $startH = (int)explode(':', $data['start_time'])[0];
        $endH = (int)explode(':', $data['end_time'])[0];
        if (!isset($data['is_night_shift'])) {
            $data['is_night_shift'] = ($startH >= 22 || $startH < 6 || $endH <= 6 || $endH > 22);
        }

        if (!empty($data['is_default'])) {
            // Đảm bảo chỉ có 1 ca duy nhất làm ca mặc định trong toàn doanh nghiệp
            Shift::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        Shift::create($data);

        return redirect()->back()->with('success', 'Đã thêm ca làm việc thành công.');
    }

    public function update(SaveShiftRequest $request, Shift $shift): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        $startH = (int)explode(':', $data['start_time'])[0];
        $endH = (int)explode(':', $data['end_time'])[0];
        if (!isset($data['is_night_shift'])) {
            $data['is_night_shift'] = ($startH >= 22 || $startH < 6 || $endH <= 6 || $endH > 22);
        }

        if (!empty($data['is_default'])) {
            // Đảm bảo chỉ có 1 ca duy nhất làm ca mặc định trong toàn doanh nghiệp
            Shift::where('tenant_id', $tenantId)->where('id', '!=', $shift->id)->update(['is_default' => false]);
        }

        $shift->update($data);

        return redirect()->back()->with('success', 'Đã cập nhật ca làm việc thành công.');
    }

    public function setDefault(Request $request, Shift $shift): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        if ($shift->tenant_id !== $tenantId) {
            abort(403);
        }

        Shift::where('tenant_id', $tenantId)->update(['is_default' => false]);
        $shift->update(['is_default' => true, 'is_active' => true]);

        return redirect()->back()->with('success', "Đã đặt ca [{$shift->name}] làm ca tiêu chuẩn mặc định toàn công ty.");
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        // Kiểm tra xem ca đã có lịch phân hay chưa
        if ($shift->rosters()->exists()) {
            $shift->update(['is_active' => false]);
            return redirect()->back()->with('warning', 'Ca này đã có lịch làm việc, hệ thống chuyển sang trạng thái Ngừng kích hoạt.');
        }

        $shift->delete();
        return redirect()->back()->with('success', 'Đã xóa ca làm việc thành công.');
    }
}
