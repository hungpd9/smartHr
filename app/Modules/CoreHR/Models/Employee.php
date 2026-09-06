<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'tenant_id',
        'user_code',
        'attendance_code',
        'full_name',
        'gender',
        'birth_date',
        'email',
        'phone',
        'citizen_id',
        'encrypted_citizen_id',
        'hometown',
        'permanent_address',
        'temporary_address',
        'join_date',
        'probation_end_date',
        'is_pregnant',
        'pregnancy_start_date',
        'pregnancy_weeks_at_start',
        'expected_due_date',
        'children_count',
        'tax_dependents_count',
        'status',
        'resigned_date',
        'resignation_reason',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'probation_end_date' => 'date',
        'is_pregnant' => 'boolean',
        'pregnancy_start_date' => 'date',
        'pregnancy_weeks_at_start' => 'integer',
        'expected_due_date' => 'date',
        'children_count' => 'integer',
        'tax_dependents_count' => 'integer',
        'resigned_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function careerTimelines(): HasMany
    {
        return $this->hasMany(EmployeeCareerTimeline::class);
    }

    public function timelines(): HasMany
    {
        return $this->careerTimelines();
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(\App\Modules\Rostering\Models\Roster::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(EmployeeChild::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(EmployeeCertificate::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(\App\Modules\LeavePolicy\Models\LeaveBalance::class);
    }

    public function overtimeAccumulations(): HasMany
    {
        return $this->hasMany(\App\Modules\LeavePolicy\Models\OvertimeAccumulation::class);
    }

    /**
     * Lấy dòng thời gian sự nghiệp hiện tại đang áp dụng
     */
    public function currentTimeline(): HasOne
    {
        return $this->hasOne(EmployeeCareerTimeline::class)
            ->where('effective_date', '<=', Carbon::today())
            ->where(function ($query) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', Carbon::today());
            })
            ->latest('effective_date');
    }

    /**
     * Kiểm tra nhân sự có đang nuôi con dưới 12 tháng tại mốc ngày xét công hay không
     * Căn cứ: Điều 137 Khoản 1 Điểm b Bộ luật Lao động 2019
     */
    public function hasChildUnder12MonthsAt(Carbon $targetDate): bool
    {
        if ($this->gender !== 'FEMALE') {
            return false;
        }

        if ($this->relationLoaded('children')) {
            return $this->children->contains(function ($child) use ($targetDate) {
                return $child->is_active 
                    && Carbon::parse($child->birth_date)->lte($targetDate)
                    && Carbon::parse($child->birth_date)->diffInMonths($targetDate) < 12;
            });
        }

        return $this->children()
            ->where('is_active', true)
            ->whereDate('birth_date', '<=', $targetDate)
            ->get()
            ->contains(function ($child) use ($targetDate) {
                return Carbon::parse($child->birth_date)->diffInMonths($targetDate) < 12;
            });
    }

    /**
     * Tính số tuần thai tại ngày xét
     */
    public function currentPregnancyWeeksAt(Carbon $targetDate): ?int
    {
        if ($this->gender !== 'FEMALE' || !$this->is_pregnant) {
            return null;
        }

        if ($this->expected_due_date) {
            // Ngày dự sinh thường là 40 tuần (280 ngày)
            $dueDate = Carbon::parse($this->expected_due_date);
            $daysLeft = $targetDate->diffInDays($dueDate, false);
            $weeks = (int) floor((280 - $daysLeft) / 7);
            return max(0, min(42, $weeks));
        }

        if ($this->pregnancy_start_date) {
            $start = Carbon::parse($this->pregnancy_start_date);
            $days = $start->diffInDays($targetDate, false);
            if ($days < 0) return 0;
            $initialWeeks = $this->pregnancy_weeks_at_start ?: 0;
            return (int) floor($initialWeeks + ($days / 7));
        }

        return null;
    }

    /**
     * Kiểm tra lao động mang thai có chạm ngưỡng cấm làm đêm / làm thêm giờ hay không
     * Căn cứ: Điều 137 Khoản 1 Điểm a BLLĐ 2019
     * - Từ tháng thứ 06 nếu ở vùng cao, biên giới, hải đảo
     * - Từ tháng thứ 07 ở điều kiện bình thường
     */
    public function isPregnantNightRestrictedAt(Branch $branch, Carbon $targetDate): bool
    {
        if ($this->gender !== 'FEMALE' || !$this->is_pregnant) {
            return false;
        }

        $thresholdMonths = $branch->is_remote_or_island ? 6 : 7;

        if ($this->pregnancy_start_date) {
            $pregnancyStart = Carbon::parse($this->pregnancy_start_date);
            if ($pregnancyStart->greaterThan($targetDate)) {
                return false;
            }
            return $pregnancyStart->diffInMonths($targetDate) >= $thresholdMonths;
        }

        if ($this->expected_due_date) {
            $dueDate = Carbon::parse($this->expected_due_date);
            $monthsLeft = $targetDate->diffInMonths($dueDate, false);
            $pregnancyMonths = max(0, 9 - $monthsLeft);
            return $pregnancyMonths >= $thresholdMonths;
        }

        return false;
    }

    /**
     * Tính chuỗi thâm niên làm việc (VD: "2 năm 5 tháng")
     */
    public function getSeniorityTextAttribute(): string
    {
        if (!$this->join_date) {
            return 'Chưa rõ';
        }

        $endDate = $this->resigned_date ?: Carbon::today();
        $years = (int) $this->join_date->diffInYears($endDate);
        $months = (int) ($this->join_date->diffInMonths($endDate) % 12);

        if ($years === 0) {
            $totalMonths = (int) $this->join_date->diffInMonths($endDate);
            return $totalMonths > 0 ? "{$totalMonths} tháng" : "Dưới 1 tháng";
        }

        return $months > 0 ? "{$years} năm {$months} tháng" : "{$years} năm";
    }

    /**
     * Tính số năm thâm niên làm việc đủ tại một mốc thời gian (dùng cho Điều 114 BLLĐ)
     */
    public function getSeniorityYearsAt(Carbon $targetDate): int
    {
        if (!$this->join_date) {
            return 0;
        }

        $endDate = $this->resigned_date && $this->resigned_date->lt($targetDate) 
            ? $this->resigned_date 
            : $targetDate;

        if ($this->join_date->gt($endDate)) {
            return 0;
        }

        return (int) $this->join_date->diffInYears($endDate);
    }

    /**
     * Tự động tính toán và lưu cache số lượng con nhỏ & người phụ thuộc thuế
     */
    public function recalculateDependentsAndChildrenCounts(): void
    {
        $this->children_count = $this->children()->where('is_active', true)->count();
        $this->tax_dependents_count = $this->children()
            ->where('is_active', true)
            ->where('is_tax_dependent', true)
            ->count();
        $this->save();
    }
}
