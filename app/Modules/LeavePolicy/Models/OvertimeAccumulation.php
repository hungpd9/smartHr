<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class OvertimeAccumulation extends Model
{
    protected $table = 'overtime_accumulations';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'year',
        'total_ot_hours_year',
        'monthly_ot_json',
        'is_exceeded_200h',
        'is_exceeded_300h',
    ];

    protected $casts = [
        'year' => 'integer',
        'total_ot_hours_year' => 'decimal:2',
        'monthly_ot_json' => 'array',
        'is_exceeded_200h' => 'boolean',
        'is_exceeded_300h' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
