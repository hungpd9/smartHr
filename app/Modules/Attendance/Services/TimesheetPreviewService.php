<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\DailyAttendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class TimesheetPreviewService
{
    /**
     * Lấy bảng công tạm tính (kết hợp các đơn từ đang ở trạng thái PENDING)
     */
    public function getPreviewTimesheet(int $tenantId, int $employeeId, string $monthYear): array
    {
        $startOfMonth = Carbon::parse($monthYear . '-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // 1. Lấy dữ liệu công chính thức từ DB
        $officialAttendances = DailyAttendance::with(['roster.shift'])
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->work_date)->format('Y-m-d'));

        // 2. Lấy danh sách đơn từ đang chờ duyệt (PENDING)
        $pendingRequests = DB::table('approval_requests')
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('status', 'PENDING')
            ->get();

        // 3. Xây dựng danh sách ngày công tạm tính (Tất cả các ngày trong tháng)
        $previewDays = [];
        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);

        // Tổng hợp thống kê
        $totalStandardHours = 0.0;
        $totalStandardDays = 0.0;
        $totalOtHours = 0.0;
        $totalLateMinutes = 0;
        $totalPenaltyAmount = 0.0;
        $pendingCount = 0;

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $official = $officialAttendances->get($dateStr);

            $dayData = [
                'work_date' => $dateStr,
                'day_name' => $date->locale('vi')->isoFormat('dddd'),
                'is_weekend' => $date->isWeekend(),
                'roster' => $official?->roster ? [
                    'id' => $official->roster->id,
                    'shift' => $official->roster->shift ? [
                        'code' => $official->roster->shift->code,
                        'name' => $official->roster->shift->name,
                        'start_time' => $official->roster->shift->start_time,
                        'end_time' => $official->roster->shift->end_time,
                    ] : null,
                ] : null,
                'actual_in' => $official?->actual_in ? Carbon::parse($official->actual_in)->format('H:i:s') : null,
                'actual_out' => $official?->actual_out ? Carbon::parse($official->actual_out)->format('H:i:s') : null,
                'standard_hours' => $official ? (float) $official->standard_hours : 0.00,
                'late_minutes' => $official ? (int) $official->late_minutes : 0,
                'early_minutes' => $official ? (int) $official->early_minutes : 0,
                'late_penalty_amount' => $official ? (float) $official->late_penalty_amount : 0.00,
                'ot_day_hours' => $official ? (float) $official->ot_day_hours : 0.00,
                'ot_night_hours' => $official ? (float) $official->ot_night_hours : 0.00,
                'ot_weekend_hours' => $official ? (float) $official->ot_weekend_hours : 0.00,
                'ot_holiday_hours' => $official ? (float) $official->ot_holiday_hours : 0.00,
                'status' => $official ? $official->status : ($date->isWeekend() ? 'PRESENT' : 'ABSENT'),
                'is_preview_modified' => false,
                'pending_request_type' => null,
                'preview_note' => null,
            ];

            // 4. Kiểm tra xem có đơn PENDING áp dụng vào ngày này không
            foreach ($pendingRequests as $req) {
                $payload = json_decode($req->form_payload ?? '{}', true);
                $reqDate = $payload['date'] ?? $payload['from_date'] ?? null;

                if ($reqDate === $dateStr) {
                    $pendingCount++;
                    $dayData['is_preview_modified'] = true;
                    $dayData['pending_request_type'] = $req->request_type;

                    if ($req->request_type === 'LEAVE') {
                        $isPaid = ($payload['leave_type'] ?? 'ANNUAL') !== 'UNPAID';
                        $dayData['status'] = $isPaid ? 'LEAVE_PAID' : 'LEAVE_UNPAID';
                        $dayData['standard_hours'] = $isPaid ? 8.00 : 0.00;
                        $dayData['late_minutes'] = 0;
                        $dayData['early_minutes'] = 0;
                        $dayData['late_penalty_amount'] = 0.00;
                        $dayData['preview_note'] = 'Tạm tính: Đang chờ duyệt đơn nghỉ phép ' . ($isPaid ? 'hưởng lương' : 'không lương');
                    } elseif ($req->request_type === 'OVERTIME') {
                        $otHours = (float) ($payload['hours'] ?? 2.0);
                        $dayData['ot_day_hours'] += $otHours;
                        $dayData['preview_note'] = "Tạm tính: Đang chờ duyệt đơn làm thêm giờ (+{$otHours}h)";
                    } elseif ($req->request_type === 'TIMESHEET_EDIT') {
                        if (!empty($payload['proposed_in'])) {
                            $dayData['actual_in'] = $payload['proposed_in'];
                        }
                        if (!empty($payload['proposed_out'])) {
                            $dayData['actual_out'] = $payload['proposed_out'];
                        }
                        $dayData['late_minutes'] = 0;
                        $dayData['early_minutes'] = 0;
                        $dayData['late_penalty_amount'] = 0.00;
                        $dayData['status'] = 'PRESENT';
                        $dayData['standard_hours'] = 8.00;
                        $dayData['preview_note'] = 'Tạm tính: Đang chờ duyệt đơn giải trình bổ sung quẹt thẻ';
                    }
                }
            }

            // Cộng dồn thống kê
            $totalStandardHours += $dayData['standard_hours'];
            $totalStandardDays += ($dayData['standard_hours'] >= 8.0) ? 1.0 : round($dayData['standard_hours'] / 8.0, 2);
            $totalOtHours += ($dayData['ot_day_hours'] + $dayData['ot_night_hours'] + $dayData['ot_weekend_hours'] + $dayData['ot_holiday_hours']);
            $totalLateMinutes += $dayData['late_minutes'] + $dayData['early_minutes'];
            $totalPenaltyAmount += $dayData['late_penalty_amount'];

            $previewDays[] = $dayData;
        }

        return [
            'month_year' => $monthYear,
            'days' => $previewDays,
            'summary' => [
                'total_standard_days' => round($totalStandardDays, 2),
                'total_standard_hours' => round($totalStandardHours, 2),
                'total_ot_hours' => round($totalOtHours, 2),
                'total_late_minutes' => $totalLateMinutes,
                'total_penalty_amount' => round($totalPenaltyAmount, 2),
                'pending_requests_count' => $pendingCount,
            ],
        ];
    }
}
