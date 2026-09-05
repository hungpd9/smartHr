<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCertificate extends Model
{
    protected $table = 'employee_certificates';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'name',
        'certificate_type',
        'field_of_study',
        'issued_by',
        'issue_date',
        'expiry_date',
        'monthly_allowance',
        'one_time_reward',
        'file_url',
        'is_verified',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'monthly_allowance' => 'decimal:2',
        'one_time_reward' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'verified_by');
    }

    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }
}
