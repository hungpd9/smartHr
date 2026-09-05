<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use Carbon\Carbon;
use Closure;

class LateEarlyPipe
{
    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        // 1. Trường hợp không có dữ liệu quẹt thẻ
        if (!$context->actualIn && !$context->actualOut) {
            $isWeekend = $context->workDate->isWeekend();
            // Nếu cuối tuần mà không có ca xếp cụ thể -> không tính vắng
            if ($isWeekend && !$context->roster) {
                $context->status = 'PRESENT';
                $context->standardHours = 0.00;
                $context->addNote('Ngày nghỉ cuối tuần theo quy định.');
            } else {
                $context->status = 'ABSENT';
                $context->standardHours = 0.00;
                $context->addNote('Vắng mặt không phép (không ghi nhận quẹt thẻ).');
            }
            return $next($context);
        }

        // 2. Trường hợp thiếu quẹt vào hoặc thiếu quẹt ra
        if (!$context->actualIn || !$context->actualOut) {
            $context->status = 'LATE_EARLY';
            $context->standardHours = 4.00; // Mặc định chỉ tính nửa ngày công khi thiếu 1 lần quẹt
            $context->addNote('Chỉ tính 0.5 ngày công do thiếu dữ liệu quẹt vào hoặc ra.');
            return $next($context);
        }

        $shift = $context->shift;
        if (!$shift) {
            $context->lateMinutes = 0;
            $context->earlyMinutes = 0;
            $context->standardHours = 0.00;
            $context->status = 'PRESENT';
            return $next($context);
        }

        $dateStr = $context->workDate->format('Y-m-d');
        $shiftStart = Carbon::parse($dateStr . ' ' . $shift->start_time);
        $shiftEnd = Carbon::parse($dateStr . ' ' . $shift->end_time);

        // Xử lý ca đêm vắt qua ngày hôm sau (VD: 22:00 -> 06:00)
        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        // 3. Tính số phút đi muộn thô (Raw Late)
        $rawLate = 0;
        if ($context->actualIn->greaterThan($shiftStart)) {
            $rawLate = (int) $shiftStart->diffInMinutes($context->actualIn);
        }

        // 4. Tính số phút về sớm thô (Raw Early)
        $rawEarly = 0;
        if ($context->actualOut->lessThan($shiftEnd)) {
            $rawEarly = (int) $context->actualOut->diffInMinutes($shiftEnd);
        }

        // 5. Hấp thụ vi phạm bằng thời gian miễn trừ con nhỏ (Child Care Exemption)
        $availableExemption = $context->childExemptionMinutes;
        $absorbedLate = min($rawLate, $availableExemption);
        $availableExemption -= $absorbedLate;

        $absorbedEarly = min($rawEarly, $availableExemption);

        $netLate = $rawLate - $absorbedLate;
        $netEarly = $rawEarly - $absorbedEarly;

        if ($absorbedLate > 0 || $absorbedEarly > 0) {
            $totalAbsorbed = $absorbedLate + $absorbedEarly;
            $context->addNote("Đã khấu trừ {$totalAbsorbed} phút vi phạm từ chế độ phúc lợi con nhỏ.");
        }

        // 6. Áp dụng khoảng thời gian miễn trừ biên độ (Grace Period) từ cấu hình
        $attendanceConfig = $context->policyVersion?->attendanceConfig;
        $graceLate = $attendanceConfig->grace_period_late_minutes ?? 5;
        $graceEarly = $attendanceConfig->grace_period_early_minutes ?? 5;

        $context->lateMinutes = $netLate <= $graceLate ? 0 : $netLate;
        $context->earlyMinutes = $netEarly <= $graceEarly ? 0 : $netEarly;

        // 7. Tính số giờ làm việc chuẩn (Standard Hours)
        // Ca thông thường 8h làm việc
        $totalShiftDuration = $shiftStart->diffInMinutes($shiftEnd) - $shift->break_minutes;
        $totalShiftHours = max(4.0, round($totalShiftDuration / 60, 2));

        if ($context->lateMinutes === 0 && $context->earlyMinutes === 0) {
            $context->standardHours = min(8.00, $totalShiftHours);
            $context->status = 'PRESENT';
        } else {
            $deductionHours = round(($context->lateMinutes + $context->earlyMinutes) / 60, 2);
            $context->standardHours = max(0.00, round($totalShiftHours - $deductionHours, 2));
            $context->status = 'LATE_EARLY';
        }

        return $next($context);
    }
}
