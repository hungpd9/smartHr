<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class ComplianceAlert extends Model
{
    protected $table = 'compliance_alerts';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'alert_type',
        'severity',
        'title',
        'description',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'resolved_by');
    }
}
