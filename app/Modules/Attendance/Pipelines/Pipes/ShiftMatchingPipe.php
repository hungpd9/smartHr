<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Models\Shift;
use Carbon\Carbon;
use Closure;

class ShiftMatchingPipe
{
    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        $dateStr = $context->workDate->format('Y-m-d');

        // 1. Kiểm tra lịch phân ca trước (Rosters)
        $roster = Roster::with('shift')
            ->where('tenant_id', $context->tenantId)
            ->where('employee_id', $context->employeeId)
            ->where('roster_date', $dateStr)
            ->first();

        if ($roster && $roster->shift) {
            $context->roster = $roster;
            $context->shift = $roster->shift;
            return $next($context);
        }

        // 2. Nếu là ngày cuối tuần mà không có ca phân công trước -> Coi là làm thêm giờ cuối tuần (OT)
        if ($context->workDate->isWeekend()) {
            $context->shift = null;
            $context->addNote('Làm thêm giờ ngày nghỉ cuối tuần (không có lịch ca chuẩn).');
            return $next($context);
        }

        // 3. Nếu ngày thường không có lịch xếp ca, tự động đối khớp ca tối ưu nhất từ danh mục Shifts
        $shifts = Shift::where('tenant_id', $context->tenantId)
            ->where('is_active', true)
            ->get();

        if ($shifts->isEmpty()) {
            $context->addNote('Cảnh báo: Hệ thống chưa có danh mục ca làm việc (Shifts).');
            return $next($context);
        }

        if ($context->actualIn) {
            $inTime = $context->actualIn->format('H:i:s');
            // Tìm ca có giờ bắt đầu gần với actualIn nhất
            $bestShift = null;
            $minDiff = PHP_INT_MAX;

            foreach ($shifts as $s) {
                $shiftStart = Carbon::parse($dateStr . ' ' . $s->start_time);
                $diff = abs($context->actualIn->diffInMinutes($shiftStart));
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $bestShift = $s;
                }
            }

            $context->shift = $bestShift;
            $context->addNote("Tự động khớp ca dựa vào giờ vào: {$bestShift->name}");
        } else {
            // Mặc định ca hành chính HC_STANDARD hoặc ca đầu tiên
            $defaultShift = $shifts->firstWhere('code', 'HC_STANDARD') ?? $shifts->first();
            $context->shift = $defaultShift;
        }

        return $next($context);
    }
}
