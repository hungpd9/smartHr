<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildWelfarePolicy extends Model
{
    protected $table = 'child_welfare_policies';

    protected $fillable = [
        'policy_version_id',
        'name',
        'min_age_months',
        'max_age_months',
        'daily_exemption_minutes',
        'pay_type',
        'prohibit_night_shift',
        'prohibit_overtime',
        'is_statutory',
        'is_active',
    ];

    protected $casts = [
        'min_age_months' => 'integer',
        'max_age_months' => 'integer',
        'daily_exemption_minutes' => 'integer',
        'prohibit_night_shift' => 'boolean',
        'prohibit_overtime' => 'boolean',
        'is_statutory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class);
    }
}
