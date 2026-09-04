<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePolicyConfig extends Model
{
    protected $table = 'attendance_policy_configs';

    protected $fillable = [
        'policy_version_id',
        'grace_period_late_minutes',
        'grace_period_early_minutes',
        'leave_unit_hours',
        'early_leave_unit_hours',
        'min_rest_hours_between_shifts',
        'min_weekly_rest_hours',
        'max_weekly_standard_hours',
        'ot_to_rostered_rate',
        'compensation_rate',
        'penalty_tiers_json',
        'frequency_penalty_json',
    ];

    protected $casts = [
        'grace_period_late_minutes' => 'integer',
        'grace_period_early_minutes' => 'integer',
        'leave_unit_hours' => 'decimal:2',
        'early_leave_unit_hours' => 'decimal:2',
        'min_rest_hours_between_shifts' => 'integer',
        'min_weekly_rest_hours' => 'integer',
        'max_weekly_standard_hours' => 'integer',
        'ot_to_rostered_rate' => 'decimal:2',
        'compensation_rate' => 'decimal:2',
        'penalty_tiers_json' => 'array',
        'frequency_penalty_json' => 'array',
    ];

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class);
    }
}
