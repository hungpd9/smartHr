<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Rostering\Models\Roster;

class DailyAttendance extends Model
{
    protected $table = 'daily_attendances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'work_date',
        'roster_id',
        'actual_in',
        'actual_out',
        'standard_hours',
        'late_minutes',
        'early_minutes',
        'late_penalty_amount',
        'ot_day_hours',
        'ot_night_hours',
        'ot_weekend_hours',
        'ot_holiday_hours',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'actual_in' => 'datetime',
        'actual_out' => 'datetime',
        'standard_hours' => 'decimal:2',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
        'late_penalty_amount' => 'decimal:2',
        'ot_day_hours' => 'decimal:2',
        'ot_night_hours' => 'decimal:2',
        'ot_weekend_hours' => 'decimal:2',
        'ot_holiday_hours' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }
}
