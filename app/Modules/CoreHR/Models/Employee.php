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
        'full_name',
        'gender',
        'birth_date',
        'email',
        'phone',
        'encrypted_citizen_id',
        'join_date',
        'is_pregnant',
        'pregnancy_start_date',
        'child_birth_date',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'is_pregnant' => 'boolean',
        'pregnancy_start_date' => 'date',
        'child_birth_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function careerTimelines(): HasMany
    {
        return $this->hasMany(EmployeeCareerTimeline::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
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
        if ($this->gender !== 'FEMALE' || !$this->child_birth_date) {
            return false;
        }

        $birthDate = Carbon::parse($this->child_birth_date);
        if ($birthDate->greaterThan($targetDate)) {
            return false;
        }

        // Tính số tháng chính xác
        $diffMonths = $birthDate->diffInMonths($targetDate);
        return $diffMonths < 12;
    }

    /**
     * Kiểm tra lao động mang thai có chạm ngưỡng cấm làm đêm / làm thêm giờ hay không
     * Căn cứ: Điều 137 Khoản 1 Điểm a BLLĐ 2019
     * - Từ tháng thứ 06 nếu ở vùng cao, biên giới, hải đảo
     * - Từ tháng thứ 07 ở điều kiện bình thường
     */
    public function isPregnantNightRestrictedAt(Branch $branch, Carbon $targetDate): bool
    {
        if ($this->gender !== 'FEMALE' || !$this->is_pregnant || !$this->pregnancy_start_date) {
            return false;
        }

        $pregnancyStart = Carbon::parse($this->pregnancy_start_date);
        if ($pregnancyStart->greaterThan($targetDate)) {
            return false;
        }

        $pregnancyMonths = $pregnancyStart->diffInMonths($targetDate);
        $threshold = $branch->is_remote_or_island ? 6 : 7;

        return $pregnancyMonths >= $threshold;
    }
}
