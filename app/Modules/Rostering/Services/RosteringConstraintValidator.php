<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Workflow\Models\ApprovalRequest;

class RosteringConstraintValidator
{
    /**
     * Kiểm tra toàn diện các ràng buộc cứng và mềm theo Bộ luật Lao động Việt Nam 2019
     * 
     * @return array{
     *    is_valid: bool,
     *    is_hard_violation: bool,
     *    hard_violations: array<string>,
     *    warnings: array<string>,
     *    notes: array<string>
     * }
     */
    public function validateShiftAssignment(
        int $employeeId,
        int $shiftId,
        CarbonInterface|string $date
    ): array {
        $targetDate = is_string($date) ? Carbon::parse($date) : $date->copy();
        $shift = Shift::findOrFail($shiftId);
        $employee = Employee::with(['tenant', 'children' => fn($q) => $q->where('is_active', true)])->findOrFail($employeeId);

        $hardViolations = [];
        $warnings = [];
        $notes = [];

        // 1. HC-01: Nghỉ tối thiểu 12 giờ giữa 2 ca liên tiếp (Điều 110 BLLĐ 2019)
        $this->validateRestHoursBetweenShifts($employeeId, $shift, $targetDate, $hardViolations);

        // 2. HC-04: Bảo vệ lao động mang thai (Điều 137 Khoản 1 Điểm a BLLĐ)
        $this->validateMaternityProtection($employee, $shift, $targetDate, $hardViolations);

        // 3. HC-05: Bảo vệ nuôi con dưới 12 tháng (Điều 137 Khoản 1 Điểm b BLLĐ)
        $this->validateChildCareProtection($employee, $shift, $targetDate, $hardViolations, $notes);

        // 4. HC-03: Trần giờ làm chuẩn 48 giờ/tuần (Điều 105 BLLĐ)
        $this->validateWeeklyStandardHours($employeeId, $shift, $targetDate, $warnings);

        // 5. HC-02: Nghỉ 24 giờ liên tục mỗi tuần (Điều 111 BLLĐ)
        $this->validateWeeklyRestDay($employeeId, $shift, $targetDate, $warnings);

        $isHardViolation = count($hardViolations) > 0;

        return [
            'is_valid' => !$isHardViolation,
            'is_hard_violation' => $isHardViolation,
            'hard_violations' => $hardViolations,
            'warnings' => $warnings,
            'notes' => $notes,
        ];
    }

    /**
     * Ràng buộc HC-01: Nghỉ ít nhất 12 giờ giữa 2 ca liên tiếp (Điều 110 BLLĐ)
     */
    protected function validateRestHoursBetweenShifts(
        int $employeeId,
        Shift $newShift,
        CarbonInterface $targetDate,
        array &$hardViolations
    ): void {
        $dateStr = $targetDate->toDateString();
        $prevDateStr = $targetDate->copy()->subDay()->toDateString();
        $nextDateStr = $targetDate->copy()->addDay()->toDateString();

        // 1.1 Kiểm tra ca ngày hôm trước
        $prevRoster = Roster::where('employee_id', $employeeId)
            ->whereDate('roster_date', $prevDateStr)
            ->with('shift')
            ->first();

        if ($prevRoster && $prevRoster->shift) {
            $prevShift = $prevRoster->shift;
            
            // Tính thời điểm kết thúc của ca hôm trước
            $prevStart = Carbon::parse("{$prevDateStr} {$prevShift->start_time}");
            $prevEnd = Carbon::parse("{$prevDateStr} {$prevShift->end_time}");
            if ($prevEnd <= $prevStart) {
                $prevEnd->addDay(); // Ca vắt qua ngày hôm nay
            }

            // Thời điểm bắt đầu ca mới ngày hôm nay
            $newStart = Carbon::parse("{$dateStr} {$newShift->start_time}");

            $restHoursFromPrev = $prevEnd->diffInMinutes($newStart, false) / 60;

            if ($restHoursFromPrev < 12.0) {
                $hardViolations[] = sprintf(
                    'Vi phạm Điều 110 BLLĐ: Khoảng nghỉ từ ca hôm trước (%s kết thúc lúc %s) đến ca mới (%s) chỉ được %.1f giờ (Yêu cầu tối thiểu 12 giờ liên tục).',
                    $prevShift->name,
                    $prevEnd->format('H:i d/m'),
                    $newStart->format('H:i'),
                    max(0, $restHoursFromPrev)
                );
            }
        }

        // 1.2 Kiểm tra ca ngày hôm sau
        $nextRoster = Roster::where('employee_id', $employeeId)
            ->whereDate('roster_date', $nextDateStr)
            ->with('shift')
            ->first();

        if ($nextRoster && $nextRoster->shift) {
            $nextShift = $nextRoster->shift;

            // Thời điểm kết thúc ca mới ngày hôm nay
            $newStart = Carbon::parse("{$dateStr} {$newShift->start_time}");
            $newEnd = Carbon::parse("{$dateStr} {$newShift->end_time}");
            if ($newEnd <= $newStart) {
                $newEnd->addDay();
            }

            // Thời điểm bắt đầu ca ngày hôm sau
            $nextStart = Carbon::parse("{$nextDateStr} {$nextShift->start_time}");

            $restHoursToNext = $newEnd->diffInMinutes($nextStart, false) / 60;

            if ($restHoursToNext < 12.0) {
                $hardViolations[] = sprintf(
                    'Vi phạm Điều 110 BLLĐ: Khoảng nghỉ từ ca mới (kết thúc lúc %s) đến ca hôm sau (%s bắt đầu lúc %s) chỉ được %.1f giờ (Yêu cầu tối thiểu 12 giờ liên tục).',
                    $newEnd->format('H:i d/m'),
                    $nextShift->name,
                    $nextStart->format('H:i'),
                    max(0, $restHoursToNext)
                );
            }
        }
    }

    /**
     * Ràng buộc HC-04: Bảo vệ lao động mang thai (Điều 137 Khoản 1 Điểm a BLLĐ 2019)
     */
    protected function validateMaternityProtection(
        Employee $employee,
        Shift $shift,
        CarbonInterface $targetDate,
        array &$hardViolations
    ): void {
        // Chỉ áp dụng với ca đêm hoặc tăng ca
        if (!$shift->is_night_shift) {
            return;
        }

        if ($employee->gender !== 'FEMALE') {
            return;
        }

        // Kiểm tra xem nhân viên có đang trong thai kỳ được ghi nhận không
        // Căn cứ theo thông tin thai sản ghi nhận trong metadata hoặc cờ thai kỳ
        $isPregnant = (bool)($employee->is_pregnant ?? false);
        $pregnancyMonth = (int)($employee->pregnancy_month ?? 0);

        if (!$isPregnant) {
            return;
        }

        // Kiểm tra chi nhánh có phải vùng sâu/vùng xa/hải đảo không
        $isRemoteOrIsland = false;
        $activeTimeline = $employee->timelines()
            ->where('effective_date', '<=', $targetDate->toDateString())
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $targetDate->toDateString());
            })
            ->with('department.branch')
            ->first();

        if ($activeTimeline && $activeTimeline->department && $activeTimeline->department->branch) {
            $isRemoteOrIsland = (bool)$activeTimeline->department->branch->is_remote_or_island;
        }

        $thresholdMonth = $isRemoteOrIsland ? 6 : 7;

        if ($pregnancyMonth >= $thresholdMonth) {
            $locationNote = $isRemoteOrIsland ? ' (Chi nhánh vùng sâu/vùng xa/hải đảo: áp dụng từ tháng thứ 6)' : ' (áp dụng từ tháng thứ 7)';
            $hardViolations[] = sprintf(
                'Vi phạm Điều 137 Khoản 1 Điểm a BLLĐ: Nghiêm cấm tuyệt đối bố trí làm việc ban đêm (22:00 - 06:00) cho lao động nữ mang thai từ tháng thứ %d%s. Nhân viên đang ở tháng thứ %d.',
                $thresholdMonth,
                $locationNote,
                $pregnancyMonth
            );
        }
    }

    /**
     * Ràng buộc HC-05: Bảo vệ nuôi con dưới 12 tháng (Điều 137 Khoản 1 Điểm b BLLĐ 2019)
     */
    protected function validateChildCareProtection(
        Employee $employee,
        Shift $shift,
        CarbonInterface $targetDate,
        array &$hardViolations,
        array &$notes
    ): void {
        if (!$shift->is_night_shift) {
            return;
        }

        // 1. Kiểm tra con nhỏ dưới 12 tháng tuổi
        $hasChildUnder12m = false;
        $youngestChildAgeMonths = null;

        // Ưu tiên tra cứu từ bảng employee_children
        if ($employee->children && $employee->children->count() > 0) {
            foreach ($employee->children as $child) {
                if ($child->birth_date) {
                    $birthDate = Carbon::parse($child->birth_date);
                    $ageMonths = $birthDate->diffInMonths($targetDate);
                    if ($ageMonths < 12) {
                        $hasChildUnder12m = true;
                        if ($youngestChildAgeMonths === null || $ageMonths < $youngestChildAgeMonths) {
                            $youngestChildAgeMonths = $ageMonths;
                        }
                    }
                }
            }
        }

        // Fallback kiểm tra child_birth_date trên employees
        if (!$hasChildUnder12m && $employee->child_birth_date) {
            $birthDate = Carbon::parse($employee->child_birth_date);
            $ageMonths = $birthDate->diffInMonths($targetDate);
            if ($ageMonths < 12) {
                $hasChildUnder12m = true;
                $youngestChildAgeMonths = $ageMonths;
            }
        }

        if (!$hasChildUnder12m) {
            return;
        }

        // 2. Kiểm tra xem người lao động ĐÃ CÓ ĐƠN ĐỒNG THUẬN TỰ NGUYỆN được duyệt hay chưa
        // Tra cứu trong bảng approval_requests
        $hasApprovedConsent = ApprovalRequest::where('employee_id', $employee->id)
            ->whereIn('request_type', ['NIGHT_SHIFT', 'OVERTIME'])
            ->where('is_voluntary_consent', true)
            ->where('status', 'APPROVED')
            ->whereDate('created_at', '<=', $targetDate)
            ->exists();

        if ($hasApprovedConsent) {
            $notes[] = sprintf(
                'Tuân thủ Điều 137 Khoản 1 Điểm b: Nhân viên nuôi con nhỏ (%d tháng) nhưng ĐÃ CÓ văn bản đồng thuận tự nguyện làm việc ban đêm được duyệt hợp lệ.',
                $youngestChildAgeMonths ?? 0
            );
        } else {
            $hardViolations[] = sprintf(
                'Vi phạm Điều 137 Khoản 1 Điểm b BLLĐ: Nghiêm cấm phân ca đêm cho lao động nuôi con dưới 12 tháng (%d tháng tuổi) khi CHƯA CÓ đơn thỏa thuận/đồng thuận tự nguyện được phê duyệt.',
                $youngestChildAgeMonths ?? 0
            );
        }
    }

    /**
     * Ràng buộc HC-03: Trần giờ làm việc tiêu chuẩn 48 giờ/tuần (Điều 105 BLLĐ)
     */
    protected function validateWeeklyStandardHours(
        int $employeeId,
        Shift $newShift,
        CarbonInterface $targetDate,
        array &$warnings
    ): void {
        $startOfWeek = $targetDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = $targetDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $weekRosters = Roster::where('employee_id', $employeeId)
            ->whereBetween('roster_date', [$startOfWeek, $endOfWeek])
            ->whereDate('roster_date', '!=', $targetDate->toDateString())
            ->with('shift')
            ->get();

        $totalHours = (float)$newShift->work_hours;
        foreach ($weekRosters as $r) {
            if ($r->shift) {
                $totalHours += (float)$r->shift->work_hours;
            }
        }

        if ($totalHours > 48.0) {
            $warnings[] = sprintf(
                'Cảnh báo Điều 105 BLLĐ: Tổng số giờ làm việc trong tuần (%s đến %s) đạt %.1f giờ, vượt quá trần tiêu chuẩn 48 giờ/tuần.',
                Carbon::parse($startOfWeek)->format('d/m'),
                Carbon::parse($endOfWeek)->format('d/m'),
                $totalHours
            );
        }
    }

    /**
     * Ràng buộc HC-02: Nghỉ hàng tuần liên tục 24 giờ (Điều 111 BLLĐ)
     */
    protected function validateWeeklyRestDay(
        int $employeeId,
        Shift $newShift,
        CarbonInterface $targetDate,
        array &$warnings
    ): void {
        $startOfWeek = $targetDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = $targetDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $rosteredDatesInWeek = Roster::where('employee_id', $employeeId)
            ->whereBetween('roster_date', [$startOfWeek, $endOfWeek])
            ->pluck('roster_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        // Thêm ngày hiện tại
        if (!in_array($targetDate->toDateString(), $rosteredDatesInWeek)) {
            $rosteredDatesInWeek[] = $targetDate->toDateString();
        }

        // Nếu làm việc cả 7 ngày trong tuần
        if (count(array_unique($rosteredDatesInWeek)) >= 7) {
            $warnings[] = 'Cảnh báo Điều 111 BLLĐ: Người lao động bị xếp làm việc cả 7 ngày liên tục trong tuần mà không có ít nhất 01 ngày nghỉ (24 giờ liên tục).';
        }
    }
}
