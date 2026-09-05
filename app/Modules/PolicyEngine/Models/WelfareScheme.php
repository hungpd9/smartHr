<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;

class WelfareScheme extends Model
{
    protected $table = 'welfare_schemes';

    protected $fillable = [
        'tenant_id',
        'policy_version_id',
        'code',
        'name',
        'category',
        'visibility',
        'is_statutory',
        'daily_exemption_minutes',
        'pay_type',
        'prohibit_night_shift',
        'prohibit_overtime',
        'additional_leave_days_per_year',
        'monthly_allowance_amount',
        'one_time_bonus_amount',
        'is_tax_exempt',
        'applicable_gender',
        'child_min_age_months',
        'child_max_age_months',
        'min_seniority_months',
        'description',
    ];

    protected $casts = [
        'daily_exemption_minutes' => 'integer',
        'is_statutory' => 'boolean',
        'prohibit_night_shift' => 'boolean',
        'prohibit_overtime' => 'boolean',
        'additional_leave_days_per_year' => 'decimal:1',
        'monthly_allowance_amount' => 'decimal:2',
        'one_time_bonus_amount' => 'decimal:2',
        'is_tax_exempt' => 'boolean',
        'child_min_age_months' => 'integer',
        'child_max_age_months' => 'integer',
        'min_seniority_months' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility', 'VISIBLE');
    }
}
