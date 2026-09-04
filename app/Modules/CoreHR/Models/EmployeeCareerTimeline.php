<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCareerTimeline extends Model
{
    protected $table = 'employee_career_timelines';

    protected $fillable = [
        'employee_id',
        'event_type',
        'department_id',
        'position_id',
        'job_title',
        'manager_id',
        'effective_date',
        'end_date',
        'decision_number',
        'decision_date',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'decision_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
