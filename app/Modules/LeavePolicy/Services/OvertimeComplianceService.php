<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Services;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\Compliance\Models\ComplianceAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OvertimeComplianceService
{
    /**
     * Đồng bộ số giờ làm thêm (OT) của nhân viên trong năm từ kết quả chấm công
     * Kiểm tra 3 tầng trần: Ngày 50%, Tháng 40h, Năm 200h/300h theo Điều 107 & NĐ 145/2020
     */
    public function syncEmployeeOvertime(Employee $employee, int $year): OvertimeAccumulation
    {
        // 1. Khởi tạo mảng 12 tháng
        $monthlyMap = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $monthlyMap[$key] = 0.00;
        }

        // 2. Tổng hợp giờ OT từ bảng daily_attendances trong năm
        $dailyRecords = DailyAttendance::where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->get();

        foreach ($dailyRecords as $record) {
            $monthKey = Carbon::parse($record->work_date)->format('m');
            
            // Tính tổng OT ngày (hỗ trợ cả các cột weekday/weekend/holiday hoặc ot_day_hours/ot_night_hours)
            $otHours = (float) ($record->ot_weekday_hours ?? 0.0) 
                + (float) ($record->ot_weekend_hours ?? 0.0) 
                + (float) ($record->ot_holiday_hours ?? 0.0);

            if ($otHours === 0.0) {
                $otHours = (float) ($record->ot_day_hours ?? 0.0) 
                    + (float) ($record->ot_night_hours ?? 0.0) 
                    + (float) ($record->ot_weekend_hours ?? 0.0) 
                    + (float) ($record->ot_holiday_hours ?? 0.0);
            }

            $monthlyMap[$monthKey] = round($monthlyMap[$monthKey] + $otHours, 2);
        }

        // 3. Đối soát thêm với bảng monthly_attendances nếu có dữ liệu tổng hợp đã chốt
        $monthlyRecords = MonthlyAttendance::where('employee_id', $employee->id)
            ->where('month_year', 'like', "{$year}-%")
            ->get();

        foreach ($monthlyRecords as $mRecord) {
            $parts = explode('-', $mRecord->month_year);
            if (isset($parts[1]) && isset($monthlyMap[$parts[1]])) {
                $monthVal = (float) $mRecord->total_ot_hours;
                // Nếu monthly_attendances có giá trị lớn hơn hoặc daily rỗng, lấy theo monthly
                if ($monthVal > 0 && $monthlyMap[$parts[1]] === 0.0) {
                    $monthlyMap[$parts[1]] = $monthVal;
                }
            }
        }

        $totalYearlyOt = round((float) array_sum($monthlyMap), 2);
        $isExceeded200h = $totalYearlyOt > 200.00;
        $isExceeded300h = $totalYearlyOt > 300.00;

        return DB::transaction(function () use ($employee, $year, $totalYearlyOt, $monthlyMap, $isExceeded200h, $isExceeded300h) {
            $accumulation = OvertimeAccumulation::updateOrCreate(
                [
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'year' => $year,
                ],
                [
                    'total_ot_hours_year' => $totalYearlyOt,
                    'monthly_ot_json' => $monthlyMap,
                    'is_exceeded_200h' => $isExceeded200h,
                    'is_exceeded_300h' => $isExceeded300h,
                ]
            );

            // 4. Tự động kích hoạt các cảnh báo tuân thủ (ComplianceAlerts)
            // Cảnh báo vượt trần năm 200 giờ
            if ($isExceeded200h) {
                ComplianceAlert::updateOrCreate(
                    [
                        'tenant_id' => $employee->tenant_id,
                        'employee_id' => $employee->id,
                        'alert_type' => 'OT_ANNUAL_CAP_BREACH',
                        'is_resolved' => false,
                    ],
                    [
                        'severity' => 'CRITICAL',
                        'title' => "Vượt trần OT năm 200 giờ (Điều 107 BLLĐ)",
                        'description' => "Nhân sự {$employee->full_name} ({$employee->user_code}) đã tích lũy {$totalYearlyOt}h OT trong năm {$year}, vượt mức giới hạn 200 giờ.",
                    ]
                );
            }

            // Cảnh báo trần tháng (40 giờ / tháng)
            foreach ($monthlyMap as $mKey => $hours) {
                if ($hours > 40.00) {
                    ComplianceAlert::updateOrCreate(
                        [
                            'tenant_id' => $employee->tenant_id,
                            'employee_id' => $employee->id,
                            'alert_type' => 'OT_BREACH_RED_40H',
                            'title' => "Vi phạm trần OT tháng {$mKey}/{$year} (> 40h)",
                        ],
                        [
                            'severity' => 'CRITICAL',
                            'description' => "Nhân sự {$employee->full_name} làm thêm {$hours}h trong tháng {$mKey}/{$year}, vượt quá giới hạn 40h/tháng theo Điều 107 Khoản 2 Điểm b BLLĐ 2019.",
                            'is_resolved' => false,
                        ]
                    );
                } elseif ($hours >= 35.00) {
                    ComplianceAlert::firstOrCreate(
                        [
                            'tenant_id' => $employee->tenant_id,
                            'employee_id' => $employee->id,
                            'alert_type' => 'OT_WARNING_YELLOW_35H',
                            'title' => "Cảnh báo tiệm cận trần OT tháng {$mKey}/{$year} (35h - 40h)",
                        ],
                        [
                            'severity' => 'WARNING',
                            'description' => "Nhân sự {$employee->full_name} đã làm thêm {$hours}h trong tháng {$mKey}/{$year}, sắp chạm trần luật định 40h.",
                            'is_resolved' => false,
                        ]
                    );
                }
            }

            return $accumulation;
        });
    }

    /**
     * Kiểm tra điều kiện làm thêm giờ trong ngày (Daily Overtime Compliance)
     * - Trần 50% giờ bình thường trong ngày (VD: tối đa 4.0h cho ca 8h)
     * - Lao động nữ nuôi con dưới 12 tháng hoặc mang thai từ tháng 7 (Điều 137)
     */
    public function checkDailyOtLimit(
        Employee $employee,
        Carbon $date,
        float $proposedOtHours,
        float $standardHours = 8.0
    ): array {
        // 1. Kiểm tra trần 50% ngày làm việc (Điều 107 Khoản 2 Điểm a)
        $maxDailyAllowed = round($standardHours * 0.5, 2);
        if ($proposedOtHours > $maxDailyAllowed) {
            return [
                'allowed' => false,
                'requires_voluntary_consent' => false,
                'reason' => "Vượt trần 50% giờ làm việc bình thường trong ngày (Đề xuất: {$proposedOtHours}h, Tối đa cho phép: {$maxDailyAllowed}h theo Điều 107 Khoản 2 Điểm a BLLĐ 2019).",
            ];
        }

        // 2. Ràng buộc bảo vệ thai sản & con nhỏ (Điều 137 BLLĐ 2019)
        if ($employee->gender === 'FEMALE') {
            // Nuôi con nhỏ dưới 12 tháng
            if ($employee->hasChildUnder12MonthsAt($date)) {
                return [
                    'allowed' => false,
                    'requires_voluntary_consent' => true,
                    'reason' => "Nhân sự đang nuôi con dưới 12 tháng tuổi: Mặc định không được làm thêm giờ (Điều 137 Khoản 1 Điểm b BLLĐ 2019), trừ khi có văn bản thỏa thuận/đồng thuận tự nguyện hợp lệ.",
                ];
            }

            // Mang thai từ tháng thứ 7 (hoặc tháng thứ 6 tại vùng cao, biên giới, hải đảo)
            $branch = $employee->currentTimeline?->department?->branch;
            if ($branch && $employee->isPregnantNightRestrictedAt($branch, $date)) {
                return [
                    'allowed' => false,
                    'requires_voluntary_consent' => false,
                    'reason' => "Nhân sự đang mang thai từ tháng thứ 7: Tuyệt đối không được làm thêm giờ theo Điều 137 Khoản 1 Điểm a BLLĐ 2019.",
                ];
            }
        }

        return [
            'allowed' => true,
            'requires_voluntary_consent' => false,
            'reason' => null,
        ];
    }

    /**
     * Lấy dữ liệu KPI tổng hợp tuân thủ OT toàn doanh nghiệp trong năm
     */
    public function getTenantOtComplianceSummary(int $tenantId, int $year): array
    {
        $currentMonthKey = Carbon::today()->format('m');

        $accumulations = OvertimeAccumulation::where('tenant_id', $tenantId)
            ->where('year', $year)
            ->with(['employee.currentTimeline.department', 'employee.currentTimeline.position'])
            ->get();

        $totalEmployeesTracked = $accumulations->count();
        $totalOtHours = round((float) $accumulations->sum('total_ot_hours_year'), 2);
        $exceeded200hCount = $accumulations->where('is_exceeded_200h', true)->count();
        $exceeded300hCount = $accumulations->where('is_exceeded_300h', true)->count();

        $warningMonthlyCount = 0;
        $breachMonthlyCount = 0;

        foreach ($accumulations as $acc) {
            $monthData = $acc->monthly_ot_json ?? [];
            $currentMonthHours = (float) ($monthData[$currentMonthKey] ?? 0.0);
            if ($currentMonthHours > 40.0) {
                $breachMonthlyCount++;
            } elseif ($currentMonthHours >= 35.0) {
                $warningMonthlyCount++;
            }
        }

        $totalBreachedEmployees = $accumulations->filter(function ($acc) {
            return $acc->is_exceeded_200h || collect($acc->monthly_ot_json ?? [])->contains(fn($h) => (float) $h > 40.0);
        })->count();

        $complianceRate = $totalEmployeesTracked > 0
            ? round((($totalEmployeesTracked - $totalBreachedEmployees) / $totalEmployeesTracked) * 100, 1)
            : 100.0;

        return [
            'year' => $year,
            'total_employees_tracked' => $totalEmployeesTracked,
            'total_ot_hours' => $totalOtHours,
            'exceeded_200h_count' => $exceeded200hCount,
            'exceeded_300h_count' => $exceeded300hCount,
            'warning_monthly_count' => $warningMonthlyCount,
            'breach_monthly_count' => $breachMonthlyCount,
            'compliance_rate' => $complianceRate,
        ];
    }
}
