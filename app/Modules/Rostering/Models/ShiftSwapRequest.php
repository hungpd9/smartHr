<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Employee;

class ShiftSwapRequest extends Model
{
    protected $table = 'shift_swap_requests';

    protected $fillable = [
        'requester_id',
        'target_employee_id',
        'requester_roster_id',
        'target_roster_id',
        'swap_type',
        'reason',
        'is_legal_compliant',
        'violation_warnings',
        'status',
    ];

    protected $casts = [
        'is_legal_compliant' => 'boolean',
        'violation_warnings' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }

    public function requesterRoster(): BelongsTo
    {
        return $this->belongsTo(Roster::class, 'requester_roster_id');
    }

    public function targetRoster(): BelongsTo
    {
        return $this->belongsTo(Roster::class, 'target_roster_id');
    }
}
