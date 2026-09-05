<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeChild extends Model
{
    protected $table = 'employee_children';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'full_name',
        'gender',
        'birth_date',
        'birth_certificate_number',
        'is_tax_dependent',
        'tax_dependent_code',
        'tax_dependent_start_date',
        'tax_dependent_end_date',
        'has_monthly_allowance',
        'monthly_allowance_amount',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'tax_dependent_start_date' => 'date',
        'tax_dependent_end_date' => 'date',
        'is_tax_dependent' => 'boolean',
        'has_monthly_allowance' => 'boolean',
        'monthly_allowance_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Tính tuổi theo tháng tại một ngày xét công cụ thể
     */
    public function ageInMonthsAt(Carbon $targetDate): int
    {
        if (!$this->birth_date || $this->birth_date->greaterThan($targetDate)) {
            return 0;
        }

        return (int) $this->birth_date->diffInMonths($targetDate);
    }

    /**
     * Chuỗi mô tả tuổi thân thiện (VD: "8 tháng tuổi", "3 tuổi 5 tháng")
     */
    public function getAgeTextAttribute(): string
    {
        if (!$this->birth_date) {
            return 'Chưa rõ';
        }

        $now = Carbon::today();
        if ($this->birth_date->greaterThan($now)) {
            return 'Dự sinh tương lai';
        }

        $years = (int) $this->birth_date->diffInYears($now);
        $months = (int) ($this->birth_date->diffInMonths($now) % 12);

        if ($years === 0) {
            $totalMonths = (int) $this->birth_date->diffInMonths($now);
            return $totalMonths > 0 ? "{$totalMonths} tháng tuổi" : "Sơ sinh (< 1 tháng)";
        }

        return $months > 0 ? "{$years} tuổi {$months} tháng" : "{$years} tuổi";
    }

    /**
     * Nhận diện các chính sách chế độ áp dụng
     */
    public function getApplicablePoliciesAttribute(): array
    {
        $now = Carbon::today();
        $ageMonths = $this->ageInMonthsAt($now);
        $policies = [];

        if ($ageMonths < 12) {
            $policies[] = [
                'type' => 'STATUTORY_UNDER_12M',
                'name' => 'Chế độ Điều 137 BLLĐ (<12 tháng)',
                'badge' => 'danger',
                'description' => 'Miễn trừ 60p có lương/ngày, cấm ca đêm & OT',
            ];
        } elseif ($ageMonths <= 132) { // 11 tuổi
            $policies[] = [
                'type' => 'COMPANY_CHILD_CARE',
                'name' => 'Chế độ con nhỏ (1-11 tuổi)',
                'badge' => 'warning',
                'description' => 'Miễn trừ 30p có lương/ngày theo quy chế',
            ];
        }

        if ($this->has_monthly_allowance) {
            $policies[] = [
                'type' => 'ALLOWANCE',
                'name' => 'Trợ cấp con nhỏ',
                'badge' => 'success',
                'description' => number_format((float) $this->monthly_allowance_amount) . ' đ/tháng',
            ];
        }

        if ($this->is_tax_dependent) {
            $policies[] = [
                'type' => 'TAX_DEDUCTION',
                'name' => 'Giảm trừ thuế TNCN',
                'badge' => 'primary',
                'description' => 'Giảm trừ gia cảnh 4.400.000 đ/tháng',
            ];
        }

        return $policies;
    }
}
