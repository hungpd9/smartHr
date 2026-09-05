<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use Carbon\Carbon;
use Closure;

class TieredOvertimePipe
{
    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        if (!$context->actualIn || !$context->actualOut) {
            return $next($context);
        }

        $shift = $context->shift;
        $dateStr = $context->workDate->format('Y-m-d');

        // 1. Nếu làm việc vào ngày Lễ/Tết (Điều 112 BLLĐ 2019)
        if ($context->isHoliday) {
            $totalWorkedMinutes = (int) $context->actualIn->diffInMinutes($context->actualOut);
            $breakMinutes = $shift ? $shift->break_minutes : 0;
            $netMinutes = max(0, $totalWorkedMinutes - $breakMinutes);

            $context->otHolidayHours = round($netMinutes / 60, 2);
            $context->status = 'HOLIDAY';
            $context->addNote("Làm thêm giờ ngày Lễ/Tết (hệ số 3.0x): {$context->otHolidayHours} giờ.");
            return $next($context);
        }

        // 2. Nếu làm việc vào cuối tuần không có lịch ca bắt buộc
        $isWeekend = $context->workDate->isWeekend();
        if ($isWeekend && (!$context->roster || $context->roster->shift->code === 'OT_WEEKEND')) {
            $totalWorkedMinutes = (int) $context->actualIn->diffInMinutes($context->actualOut);
            $breakMinutes = $shift ? $shift->break_minutes : 0;
            $netMinutes = max(0, $totalWorkedMinutes - $breakMinutes);

            $context->otWeekendHours = round($netMinutes / 60, 2);
            $context->addNote("Làm thêm giờ ngày nghỉ hàng tuần (hệ số 2.0x): {$context->otWeekendHours} giờ.");
            return $next($context);
        }

        // 3. Làm thêm giờ sau ca chuẩn ngày thường (OT Ngày thường & Ca đêm)
        if ($shift) {
            $shiftEnd = Carbon::parse($dateStr . ' ' . $shift->end_time);
            if ($shift->is_night_shift || $shiftEnd->lessThan(Carbon::parse($dateStr . ' ' . $shift->start_time))) {
                $shiftEnd->addDay();
            }

            // Chỉ tính OT nếu ở lại sau ca ít nhất 30 phút
            if ($context->actualOut->greaterThan($shiftEnd->copy()->addMinutes(30))) {
                $otMinutes = (int) $shiftEnd->diffInMinutes($context->actualOut);
                $otHours = round($otMinutes / 60, 2);

                // Kiểm tra khung giờ đêm (từ 22:00 đến 06:00 sáng hôm sau)
                $nightStart = Carbon::parse($dateStr . ' 22:00:00');
                if ($context->actualOut->greaterThan($nightStart)) {
                    $nightMinutes = min($otMinutes, (int) $nightStart->diffInMinutes($context->actualOut));
                    $dayMinutes = max(0, $otMinutes - $nightMinutes);

                    $context->otNightHours = round($nightMinutes / 60, 2);
                    $context->otDayHours = round($dayMinutes / 60, 2);
                } else {
                    $context->otDayHours = $otHours;
                }

                $context->addNote("Làm thêm giờ sau ca: Ngày={$context->otDayHours}h, Đêm={$context->otNightHours}h.");
            }
        }

        return $next($context);
    }
}
