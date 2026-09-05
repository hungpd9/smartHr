<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionAssignment extends Model
{
    protected $table = 'permission_assignments';

    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'subject_role',
        'module_code',
        'can_view',
        'can_manage',
        'can_approve',
        'notes',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_manage' => 'boolean',
        'can_approve' => 'boolean',
        'subject_id' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
