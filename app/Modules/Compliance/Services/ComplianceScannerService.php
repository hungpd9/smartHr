<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Services;

use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\CoreHR\Models\EmployeeContract;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\Rostering\Models\Roster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComplianceScannerService
{
    public function __construct(
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Quét toàn diện 8 quy tắc tuân thủ BLLĐ cho toàn bộ nhân sự của một Tenant
     */
    public function scanAll(int $tenantId, ?string $targetDate = null): array
    {
        $date = $targetDate ? Carbon::parse($targetDate) : Carbon::today();

        $otCount = $this->scanOvertimeCaps($tenantId, $date);
        $restIntervalCount = $this->scanRestIntervals($tenantId, $date);
        $weeklyRestCount = $this->scanWeeklyRest($tenantId, $date);
        $maternityCount = $this->scanMaternityProtections($tenantId, $date);
        $contractCount = $this->scanExpiringContracts($tenantId, $date);
        $burnoutCount = $this->scanBurnoutRisks($tenantId, $date);

        $totalGenerated = $otCount + $restIntervalCount + $weeklyRestCount + $maternityCount + $contractCount + $burnoutCount;

        return [
            'success' => true,
            'scanned_at' => Carbon::now()->toIso8601String(),
            'target_date' => $date->toDateString(),
            'total_alerts_generated_or_updated' => $totalGenerated,
            'details' => [
                'overtime_cap_alerts' => $otCount,
                'rest_between_shifts_alerts' => $restIntervalCount,
                'weekly_rest_alerts' => $weeklyRestCount,
                'maternity_protection_alerts' => $maternityCount,
                'contract_expiring_alerts' => $contractCount,
                'burnout_risk_alerts' => $burnoutCount,
            ],
            'stats' => $this->getComplianceDashboardStats($tenantId),
        ];
    }

    /**
     * 1. Quét vi phạm trần làm thêm giờ tháng (35h/40h) và trần năm (200h/300h)
     * Căn cứ: Điều 107 BLLĐ 2019 & Nghị định 145/2020/NĐ-CP
     */
    public function scanOvertimeCaps(int $tenantId, Carbon $targetDate): int
    {
        $monthYear = $targetDate->format('Y-m');
        $year = (int) $targetDate->format('Y');
        $alertCount = 0;

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'RESIGNED')
            ->get();

        foreach ($employees as $employee) {
            // Lấy số giờ OT tích lũy tháng & năm
            $accumulation = OvertimeAccumulation::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->first();

            $monthOt = null;
            if ($accumulation && is_array($accumulation->monthly_ot_json) && isset($accumulation->monthly_ot_json[$monthYear])) {
                $monthOt = (float) $accumulation->monthly_ot_json[$monthYear];
            }

            // Nếu chưa có bảng accumulation, fallback tính từ monthly_attendances hoặc daily_attendances
            if ($monthOt === null) {
                $monthly = MonthlyAttendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('month_year', $monthYear)
                    ->first();

                $monthOt = $monthly ? (float) $monthly->total_ot_hours : (float) DailyAttendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->whereBetween('work_date', [$targetDate->copy()->startOfMonth()->toDateString(), $targetDate->copy()->endOfMonth()->toDateString()])
                    ->sum(DB::raw('ot_day_hours + ot_night_hours + ot_weekend_hours + ot_holiday_hours'));
            }

            $yearOt = $accumulation ? (float) $accumulation->total_ot_hours_year : $monthOt;

            // Kiểm tra trần OT tháng:
            if ($monthOt >= 40.0) {
                $this->createOrUpdateAlert(
                    tenantId: $tenantId,
                    employeeId: $employee->id,
                    alertType: 'OT_BREACH_RED_40H',
                    severity: 'CRITICAL',
                    title: "Vi phạm trần làm thêm giờ tháng (>= 40h) - {$employee->full_name} ({$employee->user_code})",
                    description: "Nhân viên {$employee->full_name} đã tích lũy {$monthOt} giờ làm thêm trong tháng {$monthYear}, vượt quá giới hạn tối đa 40 giờ/tháng theo Điều 107 Khoản 2 Điểm b BLLĐ 2019 và Nghị định 145/2020/NĐ-CP. Hành vi này có thể bị xử phạt vi phạm hành chính từ 10.000.000đ đến 75.000.000đ theo Điều 18 Nghị định 12/2022/NĐ-CP. Đề nghị dừng ngay việc bố trí làm thêm giờ trong tháng."
                );
                $alertCount++;
            } elseif ($monthOt >= 35.0) {
                $this->createOrUpdateAlert(
                    tenantId: $tenantId,
                    employeeId: $employee->id,
                    alertType: 'OT_WARNING_YELLOW_35H',
                    severity: 'WARNING',
                    title: "Cảnh báo cận trần làm thêm giờ tháng (>= 35h) - {$employee->full_name} ({$employee->user_code})",
                    description: "Nhân viên {$employee->full_name} đã tích lũy {$monthOt} giờ làm thêm trong tháng {$monthYear}. Sắp chạm ngưỡng đỏ trần luật định (40 giờ/tháng). Đề nghị Quản lý bộ phận chủ động giảm tải và điều chuyển phân ca tăng ca cho nhân sự khác."
                );
                $alertCount++;
            }

            // Kiểm tra trần OT năm (200h hoặc 300h ngành đặc thù):
            $annualCap = 200.0;
            if ($yearOt >= $annualCap) {
                $this->createOrUpdateAlert(
                    tenantId: $tenantId,
                    employeeId: $employee->id,
                    alertType: 'OT_ANNUAL_CAP_BREACH',
                    severity: 'CRITICAL',
                    title: "Vi phạm trần làm thêm giờ năm (>= {$annualCap}h) - {$employee->full_name} ({$employee->user_code})",
                    description: "Nhân viên {$employee->full_name} đã tích lũy {$yearOt} giờ làm thêm trong năm {$year}, vượt quá giới hạn 200 giờ/năm theo Điều 107 Khoản 2 Điểm c BLLĐ 2019. Đề nghị tuyệt đối không bố trí thêm giờ làm thêm cho đến hết năm tài chính."
                );
                $alertCount++;
            }
        }

        return $alertCount;
    }

    /**
     * 2. Quét vi phạm thời gian nghỉ giữa 2 ca làm việc liên tiếp ít nhất 12 giờ
     * Căn cứ: Điều 110 BLLĐ 2019
     */
    public function scanRestIntervals(int $tenantId, Carbon $targetDate): int
    {
        $alertCount = 0;
        $prevDate = $targetDate->copy()->subDay()->toDateString();
        $curDate = $targetDate->toDateString();

        // Lấy lịch phân ca của ngày hôm qua và ngày hôm nay
        $rosters = Roster::where('tenant_id', $tenantId)
            ->whereDate('roster_date', '>=', $prevDate)
            ->whereDate('roster_date', '<=', $curDate)
            ->with(['shift', 'employee'])
            ->get()
            ->groupBy('employee_id');

        foreach ($rosters as $employeeId => $empRosters) {
            $prevRoster = $empRosters->first(function ($r) use ($prevDate) {
                return Carbon::parse($r->roster_date)->format('Y-m-d') === $prevDate;
            });
            $curRoster = $empRosters->first(function ($r) use ($curDate) {
                return Carbon::parse($r->roster_date)->format('Y-m-d') === $curDate;
            });

            if (!$prevRoster?->shift || !$curRoster?->shift) {
                continue;
            }

            // Tính thời điểm kết thúc ca hôm qua và bắt đầu ca hôm nay
            $prevEnd = Carbon::parse("{$prevDate} {$prevRoster->shift->end_time}");
            if ($prevRoster->shift->end_time <= $prevRoster->shift->start_time) {
                $prevEnd->addDay();
            }

            $curStart = Carbon::parse("{$curDate} {$curRoster->shift->start_time}");

            if ($curStart > $prevEnd) {
                $restHours = round($prevEnd->diffInMinutes($curStart) / 60.0, 1);
                if ($restHours < 12.0) {
                    $employee = $prevRoster->employee ?? Employee::find($employeeId);
                    if ($employee) {
                        $this->createOrUpdateAlert(
                            tenantId: $tenantId,
                            employeeId: $employee->id,
                            alertType: 'REST_BETWEEN_SHIFTS_BREACH',
                            severity: 'CRITICAL',
                            title: "Vi phạm khoảng cách nghỉ giữa 2 ca (< 12 giờ) - {$employee->full_name} ({$employee->user_code})",
                            description: "Nhân viên {$employee->full_name} kết thúc ca làm việc trước lúc {$prevEnd->format('H:i d/m')} và bắt đầu ca kế tiếp lúc {$curStart->format('H:i d/m')}. Thời gian nghỉ chỉ đạt {$restHours} giờ, vi phạm quy định nghỉ tối thiểu 12 giờ trước khi chuyển sang ca khác theo Điều 110 BLLĐ 2019. Rủi ro xử phạt 4.000.000đ - 40.000.000đ theo Nghị định 12/2022/NĐ-CP."
                        );
                        $alertCount++;
                    }
                }
            }
        }

        return $alertCount;
    }

    /**
     * 3. Quét vi phạm nghỉ hàng tuần tối thiểu 24 giờ liên tục
     * Căn cứ: Điều 111 BLLĐ 2019
     */
    public function scanWeeklyRest(int $tenantId, Carbon $targetDate): int
    {
        $alertCount = 0;
        $endDate = $targetDate->copy()->endOfDay();
        $startDate = $targetDate->copy()->subDays(6)->startOfDay(); // Khung 7 ngày gần nhất

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'RESIGNED')
            ->get();

        foreach ($employees as $employee) {
            // Đếm số ngày có làm việc thực tế trong 7 ngày gần nhất
            $workDaysCount = DailyAttendance::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $startDate->toDateString())
                ->whereDate('work_date', '<=', $endDate->toDateString())
                ->where(function ($q) {
                    $q->where('standard_hours', '>', 0)
                      ->orWhereNotNull('actual_in');
                })
                ->count();

            // Nếu không có dữ liệu daily_attendance, kiểm tra qua rosters
            if ($workDaysCount === 0) {
                $workDaysCount = Roster::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->whereDate('roster_date', '>=', $startDate->toDateString())
                    ->whereDate('roster_date', '<=', $endDate->toDateString())
                    ->whereNotNull('shift_id')
                    ->count();
            }

            if ($workDaysCount >= 7) {
                $this->createOrUpdateAlert(
                    tenantId: $tenantId,
                    employeeId: $employee->id,
                    alertType: 'WEEKLY_REST_BREACH',
                    severity: 'WARNING',
                    title: "Vi phạm nghỉ hàng tuần (Làm việc 7 ngày liên tục) - {$employee->full_name} ({$employee->user_code})",
                    description: "Nhân viên {$employee->full_name} đã làm việc 7 ngày liên tiếp từ {$startDate->format('d/m')} đến {$endDate->format('d/m/Y')} mà không có ngày nghỉ 24 giờ liên tục theo quy định tại Điều 111 BLLĐ 2019. Đề nghị bố trí ngay ngày nghỉ bù trong tuần tới."
                );
                $alertCount++;
            }
        }

        return $alertCount;
    }

    /**
     * 4. Quét vi phạm bảo vệ thai sản làm việc ban đêm hoặc làm thêm giờ
     * Căn cứ: Điều 137 Khoản 1 Điểm a BLLĐ 2019
     */
    public function scanMaternityProtections(int $tenantId, Carbon $targetDate): int
    {
        $alertCount = 0;
        $curDateStr = $targetDate->toDateString();

        // Lọc nhân viên nữ đang mang thai
        $pregnantEmployees = Employee::where('tenant_id', $tenantId)
            ->where('gender', 'FEMALE')
            ->where('is_pregnant', true)
            ->where('status', '!=', 'RESIGNED')
            ->with(['currentTimeline.department.branch'])
            ->get();

        foreach ($pregnantEmployees as $employee) {
            // Xác định số tuần thai hiện tại
            $pregnancyWeeks = 0;
            if ($employee->pregnancy_start_date) {
                $weeksSinceLmp = Carbon::parse($employee->pregnancy_start_date)->diffInWeeks($targetDate);
                $pregnancyWeeks = (int) $weeksSinceLmp + (int) ($employee->pregnancy_weeks_at_start ?? 0);
            } elseif ($employee->expected_due_date) {
                $weeksToDue = $targetDate->diffInWeeks(Carbon::parse($employee->expected_due_date), false);
                $pregnancyWeeks = max(0, 40 - (int) $weeksToDue);
            }

            $pregnancyMonths = round($pregnancyWeeks / 4.3, 1);

            // Kiểm tra điều kiện vùng sâu, vùng xa, biên giới, hải đảo
            $isRemoteOrIsland = false;
            $branch = $employee->currentTimeline?->department?->branch;
            if ($branch && isset($branch->is_remote_or_island)) {
                $isRemoteOrIsland = (bool) $branch->is_remote_or_island;
            }

            // Ngưỡng cấm: Từ tháng thứ 7 (tuần 28) hoặc từ tháng thứ 6 (tuần 24) nếu vùng sâu vùng xa
            $thresholdWeeks = $isRemoteOrIsland ? 24 : 28;
            $thresholdMonthText = $isRemoteOrIsland ? 'tháng thứ 6' : 'tháng thứ 7';

            if ($pregnancyWeeks >= $thresholdWeeks) {
                // Kiểm tra xem hôm nay có làm ca đêm hoặc có làm thêm giờ không
                $rosterToday = Roster::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('roster_date', $curDateStr)
                    ->with('shift')
                    ->first();

                $isNightShift = false;
                if ($rosterToday?->shift) {
                    $isNightShift = (bool) $rosterToday->shift->is_night_shift ||
                        ($rosterToday->shift->start_time >= '22:00:00' || $rosterToday->shift->end_time <= '06:00:00');
                }

                $dailyAtt = DailyAttendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('work_date', $curDateStr)
                    ->first();

                $hasOt = $dailyAtt && ($dailyAtt->ot_day_hours > 0 || $dailyAtt->ot_night_hours > 0 || $dailyAtt->ot_weekend_hours > 0 || $dailyAtt->ot_holiday_hours > 0);
                $hasNightWork = $dailyAtt && ((float) $dailyAtt->ot_night_hours > 0);

                if ($isNightShift || $hasOt || $hasNightWork) {
                    $violationDetail = [];
                    if ($isNightShift || $hasNightWork) {
                        $violationDetail[] = 'Làm việc ban đêm (22:00 - 06:00)';
                    }
                    if ($hasOt) {
                        $violationDetail[] = 'Làm thêm giờ (OT)';
                    }
                    $violationStr = implode(', ', $violationDetail);

                    $this->createOrUpdateAlert(
                        tenantId: $tenantId,
                        employeeId: $employee->id,
                        alertType: 'PREGNANT_NIGHT_BREACH',
                        severity: 'CRITICAL',
                        title: "Vi phạm nghiêm trọng: Lao động mang thai {$thresholdMonthText} làm đêm/OT - {$employee->full_name}",
                        description: "Nhân viên {$employee->full_name} đang mang thai tuần thứ {$pregnancyWeeks} (khoảng {$pregnancyMonths} tháng) được phân công hoặc làm việc: [{$violationStr}] vào ngày {$targetDate->format('d/m/Y')}. Hành vi này bị nghiêm cấm tuyệt đối theo Điều 137 Khoản 1 Điểm a BLLĐ 2019. Mức phạt tiền người sử dụng lao động từ 20.000.000đ đến 40.000.000đ theo Điều 28 Nghị định 12/2022/NĐ-CP. Đề nghị chuyển ngay sang ca ngày bình thường."
                    );
                    $alertCount++;
                }
            }
        }

        return $alertCount;
    }

    /**
     * 5. Quét hợp đồng lao động có thời hạn sắp hết hạn trong vòng 30 ngày
     * Căn cứ: Điều 20 & Điều 45 BLLĐ 2019
     */
    public function scanExpiringContracts(int $tenantId, Carbon $targetDate): int
    {
        $alertCount = 0;
        $todayStr = $targetDate->toDateString();
        $next30DaysStr = $targetDate->copy()->addDays(30)->toDateString();

        $expiringContracts = EmployeeContract::whereHas('employee', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->where('status', '!=', 'RESIGNED');
            })
            ->where('status', 'ACTIVE')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$todayStr, $next30DaysStr])
            ->with('employee')
            ->get();

        foreach ($expiringContracts as $contract) {
            $employee = $contract->employee;
            if (!$employee) {
                continue;
            }

            $endDate = Carbon::parse($contract->end_date);
            $daysLeft = $targetDate->diffInDays($endDate, false);

            $this->createOrUpdateAlert(
                tenantId: $tenantId,
                employeeId: $employee->id,
                alertType: 'CONTRACT_EXPIRING_30D',
                severity: 'WARNING',
                title: "Hợp đồng lao động sắp hết hạn trong {$daysLeft} ngày - {$employee->full_name} ({$contract->contract_number})",
                description: "Hợp đồng lao động loại {$contract->contract_type} (Số: {$contract->contract_number}) của nhân viên {$employee->full_name} sẽ hết hạn vào ngày {$endDate->format('d/m/Y')} (còn {$daysLeft} ngày). Đề nghị Phòng Nhân sự hoàn tất đánh giá và gửi thông báo văn bản trước ít nhất 30 ngày theo quy định tại Điều 20 và Điều 45 BLLĐ 2019."
            );
            $alertCount++;
        }

        return $alertCount;
    }

    /**
     * 6. Quét nguy cơ kiệt sức do làm việc cường độ cao (Burnout Risk)
     * Căn cứ: Sức khỏe nghề nghiệp & an toàn vệ sinh lao động
     */
    public function scanBurnoutRisks(int $tenantId, Carbon $targetDate): int
    {
        $alertCount = 0;
        $startDate = $targetDate->copy()->subDays(6)->toDateString();
        $endDate = $targetDate->toDateString();

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'RESIGNED')
            ->get();

        foreach ($employees as $employee) {
            $weeklyAttendances = DailyAttendance::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $startDate)
                ->whereDate('work_date', '<=', $endDate)
                ->get();

            $totalWeeklyOt = (float) $weeklyAttendances->sum(function ($att) {
                return (float) $att->ot_day_hours + (float) $att->ot_night_hours + (float) $att->ot_weekend_hours + (float) $att->ot_holiday_hours;
            });

            $consecutiveDays = $weeklyAttendances->filter(fn($att) => (float)$att->standard_hours > 0 || $att->actual_in !== null)->count();
            $nightShiftsCount = $weeklyAttendances->filter(fn($att) => (float)$att->ot_night_hours > 0)->count();

            // Nguy cơ cao: Làm việc liên tục >= 6 ngày VÀ (OT tuần > 15h HOẶC làm đêm >= 3 ca)
            if ($consecutiveDays >= 6 && ($totalWeeklyOt >= 15.0 || $nightShiftsCount >= 3)) {
                $this->createOrUpdateAlert(
                    tenantId: $tenantId,
                    employeeId: $employee->id,
                    alertType: 'BURNOUT_RISK_HIGH',
                    severity: 'WARNING',
                    title: "Cảnh báo nguy cơ kiệt sức nghề nghiệp (Burnout Risk) - {$employee->full_name}",
                    description: "Nhân viên {$employee->full_name} đã làm việc {$consecutiveDays} ngày trong 7 ngày qua với tổng cộng {$totalWeeklyOt} giờ tăng ca và {$nightShiftsCount} ca đêm. Cường độ làm việc vượt mức khuyến nghị an toàn sức khỏe, dễ dẫn đến kiệt sức và sai sót vận hành. Đề nghị bố trí ngày nghỉ hồi phục."
                );
                $alertCount++;
            }
        }

        return $alertCount;
    }

    /**
     * Đánh dấu khắc phục / giải quyết cảnh báo vi phạm
     */
    public function resolveAlert(int $alertId, int $resolvedByUserId, ?string $resolutionNotes = null): ComplianceAlert
    {
        $alert = ComplianceAlert::findOrFail($alertId);

        $oldValues = [
            'is_resolved' => $alert->is_resolved,
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ];

        // Tìm employee_id tương ứng với user nếu có
        $employee = Employee::where('email', auth()->user()?->email)->first();
        $resolvedByEmployeeId = $employee?->id;

        $updateData = [
            'is_resolved' => true,
            'resolved_by' => $resolvedByEmployeeId,
            'resolved_at' => Carbon::now(),
        ];

        if ($resolutionNotes) {
            $updateData['description'] = $alert->description . "\n\n[GHI CHÚ KHẮC PHỤC]: " . trim($resolutionNotes);
        }

        $alert->update($updateData);

        // Lưu vết kiểm toán Audit Trail
        $this->auditTrailService->log(
            tenantId: $alert->tenant_id,
            actionType: 'RESOLVE_COMPLIANCE_ALERT',
            model: $alert,
            oldValues: $oldValues,
            newValues: [
                'is_resolved' => true,
                'resolved_by_user_id' => $resolvedByUserId,
                'resolved_at' => Carbon::now()->toIso8601String(),
                'resolution_notes' => $resolutionNotes,
            ]
        );

        return $alert;
    }

    /**
     * Khắc phục hàng loạt nhiều cảnh báo
     */
    public function bulkResolve(array $alertIds, int $resolvedByUserId, ?string $resolutionNotes = null): int
    {
        $count = 0;
        foreach ($alertIds as $id) {
            try {
                $this->resolveAlert((int) $id, $resolvedByUserId, $resolutionNotes);
                $count++;
            } catch (\Throwable) {
                // Bỏ qua lỗi từng bản ghi để tiếp tục
            }
        }
        return $count;
    }

    /**
     * Tổng hợp chỉ số thống kê Dashboard Tuân thủ BLLĐ
     */
    public function getComplianceDashboardStats(int $tenantId): array
    {
        $unresolvedAlerts = ComplianceAlert::where('tenant_id', $tenantId)
            ->where('is_resolved', false)
            ->get();

        $totalUnresolved = $unresolvedAlerts->count();
        $criticalCount = $unresolvedAlerts->where('severity', 'CRITICAL')->count();
        $warningCount = $unresolvedAlerts->where('severity', 'WARNING')->count();
        $infoCount = $unresolvedAlerts->where('severity', 'INFO')->count();

        $resolvedCount = ComplianceAlert::where('tenant_id', $tenantId)
            ->where('is_resolved', true)
            ->count();

        // Điểm sức khỏe tuân thủ: Bắt đầu từ 100%, trừ 15 điểm cho mỗi Critical, trừ 3 điểm cho mỗi Warning
        $penaltyPoints = ($criticalCount * 15) + ($warningCount * 3);
        $complianceScore = max(0, min(100, 100 - $penaltyPoints));

        // Phân bổ theo loại cảnh báo
        $byType = [];
        foreach ($unresolvedAlerts->groupBy('alert_type') as $type => $group) {
            $byType[$type] = $group->count();
        }

        return [
            'total_unresolved' => $totalUnresolved,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'resolved_count' => $resolvedCount,
            'compliance_score' => $complianceScore,
            'breakdown_by_type' => $byType,
        ];
    }

    /**
     * Helper tạo hoặc cập nhật cảnh báo (ngăn ngừa duplicate nếu chưa giải quyết)
     */
    protected function createOrUpdateAlert(
        int $tenantId,
        ?int $employeeId,
        string $alertType,
        string $severity,
        string $title,
        string $description
    ): ComplianceAlert {
        $alert = ComplianceAlert::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'alert_type' => $alertType,
                'is_resolved' => false,
            ],
            [
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
            ]
        );

        if ($alert->wasRecentlyCreated && in_array(strtoupper($severity), ['CRITICAL', 'WARNING'], true)) {
            try {
                $notiType = strtoupper($severity) === 'CRITICAL' ? 'COMPLIANCE_CRITICAL_ALERT' : 'COMPLIANCE_WARNING_ALERT';
                app(\App\Modules\Notification\Services\NotificationDispatcherService::class)->broadcastToRole(
                    tenantId: $tenantId,
                    roles: ['SUPER_ADMIN', 'HR_ADMIN', 'ADMIN'],
                    type: $notiType,
                    title: $title,
                    content: $description,
                    actionUrl: '/compliance',
                    payload: ['alert_id' => $alert->id, 'alert_type' => $alertType],
                    severity: strtoupper($severity)
                );
            } catch (\Throwable) {
                // Bỏ qua lỗi gửi để không gián đoạn quá trình quét
            }
        }

        return $alert;
    }
}
