<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class MonthlyAttendance extends Model
{
    protected $table = 'monthly_attendances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'month_year',
        'total_standard_days',
        'total_ot_hours',
        'total_late_minutes',
        'total_penalty_amount',
        'is_approved',
        'approved_by',
        'approved_at',
        'submission_status',
        'submission_type',
        'submitted_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'is_locked',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'total_standard_days' => 'decimal:2',
        'total_ot_hours' => 'decimal:2',
        'total_late_minutes' => 'integer',
        'total_penalty_amount' => 'decimal:2',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }
}
