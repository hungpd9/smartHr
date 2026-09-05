<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use App\Modules\Attendance\Models\RawAttendanceLog;
use Carbon\Carbon;
use Closure;

class PairInOutPipe
{
    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        // Nếu chưa nạp rawLogs, query từ DB cho ngày công
        if ($context->rawLogs->isEmpty()) {
            $startOfDay = $context->workDate->copy()->startOfDay();
            $endOfDay = $context->workDate->copy()->endOfDay();

            $context->rawLogs = RawAttendanceLog::where('tenant_id', $context->tenantId)
                ->where('employee_id', $context->employeeId)
                ->whereBetween('punch_time', [$startOfDay, $endOfDay])
                ->orderBy('punch_time')
                ->get();
        }

        $logs = $context->rawLogs;

        if ($logs->isEmpty()) {
            $context->actualIn = null;
            $context->actualOut = null;
            $context->addNote('Không có dữ liệu quẹt thẻ từ máy chấm công.');
            return $next($context);
        }

        if ($logs->count() === 1) {
            $punch = Carbon::parse($logs->first()->punch_time);
            // Nếu quẹt trước 12h trưa coi là In, thiếu Out
            if ($punch->hour < 12) {
                $context->actualIn = $punch;
                $context->actualOut = null;
                $context->isMissingCheckOut = true;
                $context->addNote('Nhân viên chỉ quẹt vào (Check-in), thiếu quẹt ra (Check-out).');
            } else {
                $context->actualIn = null;
                $context->actualOut = $punch;
                $context->isMissingCheckIn = true;
                $context->addNote('Nhân viên chỉ quẹt ra (Check-out), thiếu quẹt vào (Check-in).');
            }
            return $next($context);
        }

        // Có từ 2 lần quẹt trở lên: Lấy sớm nhất làm In, muộn nhất làm Out
        $context->actualIn = Carbon::parse($logs->first()->punch_time);
        $context->actualOut = Carbon::parse($logs->last()->punch_time);

        return $next($context);
    }
}
