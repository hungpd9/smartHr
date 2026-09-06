<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;

class AuditTrail extends Model
{
    protected $table = 'audit_trails';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'actor_id',
        'actor_type',
        'action_type',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
