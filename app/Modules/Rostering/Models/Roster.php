<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class Roster extends Model
{
    protected $table = 'rosters';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'shift_id',
        'roster_date',
        'is_published',
        'violation_flags',
        'status',
    ];

    protected $casts = [
        'roster_date' => 'date',
        'is_published' => 'boolean',
        'violation_flags' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
