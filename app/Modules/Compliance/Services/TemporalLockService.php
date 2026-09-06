<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Services;

use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class TemporalLockService
{
    /**
     * Kiểm tra xem kỳ chấm công có bị khóa hay không
     */
    public function isAttendanceLocked(int $tenantId, string|Carbon $dateOrMonth): bool
    {
        $monthYear = $dateOrMonth instanceof Carbon ? $dateOrMonth->format('Y-m') : substr($dateOrMonth, 0, 7);

        return MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Kiểm tra xem kỳ lương có bị khóa hay không
     */
    public function isPayrollLocked(int $tenantId, string|Carbon $dateOrMonth): bool
    {
        $monthYear = $dateOrMonth instanceof Carbon ? $dateOrMonth->format('Y-m') : substr($dateOrMonth, 0, 7);

        return PayrollPeriod::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Kiểm tra nhân viên đã được quản lý phê duyệt bảng công tháng hay chưa
     * Quy tắc: Nếu chưa duyệt bảng công -> Bỏ qua không tính lương
     */
    public function isEmployeeTimesheetApproved(int $tenantId, int $employeeId, string $monthYear): bool
    {
        $record = MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('month_year', $monthYear)
            ->first();

        return (bool) ($record?->is_approved);
    }

    /**
     * Kiểm tra xem một phiên bản quy chế (PolicyVersion) đã được áp dụng trong kỳ lương/công đã khóa hay chưa.
     * Nếu đã được dùng trong kỳ đã khóa -> Khóa toàn bộ sửa/xóa hồi tố!
     */
    public function isPolicyVersionLocked(PolicyVersion $version): bool
    {
        // 0. Nếu phiên bản đã được đánh dấu khóa trực tiếp
        if ($version->is_locked) {
            return true;
        }

        // 1. Kiểm tra trong các kỳ lương đã khóa có liên kết với phiên bản này
        $hasLockedPayroll = PayrollPeriod::where('tenant_id', $version->tenant_id)
            ->where('policy_version_id', $version->id)
            ->where('is_locked', true)
            ->exists();

        if ($hasLockedPayroll) {
            return true;
        }

        // 2. Kiểm tra xem có kỳ công nào đã khóa rơi vào khoảng thời gian hiệu lực của version này
        $fromMonth = $version->effective_from instanceof \Carbon\CarbonInterface
            ? $version->effective_from->format('Y-m')
            : substr((string) $version->effective_from, 0, 7);

        $toMonth = $version->effective_to
            ? ($version->effective_to instanceof \Carbon\CarbonInterface ? $version->effective_to->format('Y-m') : substr((string) $version->effective_to, 0, 7))
            : null;

        $query = MonthlyAttendance::where('tenant_id', $version->tenant_id)
            ->where('is_locked', true)
            ->where('month_year', '>=', $fromMonth);

        if ($toMonth) {
            $query->where('month_year', '<=', $toMonth);
        }

        return $query->exists();
    }

    /**
     * Ràng buộc: Chặn tạo đơn nghỉ phép hoặc OT rơi vào tháng đã khóa công
     */
    public function assertCanCreateLeaveOrOt(int $tenantId, Carbon $startDate, Carbon $endDate): void
    {
        $startMonth = $startDate->format('Y-m');
        $endMonth = $endDate->format('Y-m');

        if ($this->isAttendanceLocked($tenantId, $startMonth)) {
            throw ValidationException::withMessages([
                'start_date' => ["Kỳ chấm công tháng {$startMonth} đã được chốt và khóa sổ. Không thể tạo đơn từ hoặc can thiệp hồi tố."],
            ]);
        }

        if ($startMonth !== $endMonth && $this->isAttendanceLocked($tenantId, $endMonth)) {
            throw ValidationException::withMessages([
                'end_date' => ["Kỳ chấm công tháng {$endMonth} đã được chốt và khóa sổ. Không thể tạo đơn từ hoặc can thiệp hồi tố."],
            ]);
        }
    }

    /**
     * Ràng buộc: Chặn sửa/xóa cấu hình quy chế đã khóa
     */
    public function assertPolicyNotLocked(PolicyVersion $version): void
    {
        if ($this->isPolicyVersionLocked($version)) {
            throw ValidationException::withMessages([
                'policy_version' => [
                    "Phiên bản quy chế '{$version->name}' ({$version->code}) đã được áp dụng cho kỳ lương/công đã chốt và khóa sổ. " .
                    "Để bảo đảm tính toàn vẹn dữ liệu thuế & kiểm toán, bạn không thể sửa đổi hoặc xóa hồi tố. " .
                    "Vui lòng sử dụng tính năng 'Khởi Tạo Phiên Bản Mới (Effective Dating)' với mốc ngày bắt đầu từ kỳ chưa khóa trở đi."
                ],
            ]);
        }
    }

    /**
     * Ràng buộc: Chặn di chuyển phòng ban / nhân sự rơi vào tháng đã chốt công hoặc chốt lương
     */
    public function assertCanModifyCareerTimeline(int $tenantId, Carbon $effectiveDate): void
    {
        $month = $effectiveDate->format('Y-m');

        if ($this->isPayrollLocked($tenantId, $month)) {
            throw ValidationException::withMessages([
                'effective_date' => ["Kỳ lương tháng {$month} đã được quyết toán và khóa sổ. Không thể điều chuyển nhân sự hồi tố vào kỳ này."],
            ]);
        }

        if ($this->isAttendanceLocked($tenantId, $month)) {
            throw ValidationException::withMessages([
                'effective_date' => ["Kỳ chấm công tháng {$month} đã khóa sổ. Không thể điều chuyển nhân sự hồi tố."],
            ]);
        }
    }

    /**
     * Ràng buộc: Chặn tính toán lại khi kỳ lương đã khóa
     */
    public function assertPayrollNotLocked(int $tenantId, string $monthYear): void
    {
        if ($this->isPayrollLocked($tenantId, $monthYear)) {
            throw ValidationException::withMessages([
                'month_year' => ["Kỳ lương {$monthYear} đã được chốt và khóa sổ. Không thể tính toán lại hoặc can thiệp."],
            ]);
        }
    }
}
