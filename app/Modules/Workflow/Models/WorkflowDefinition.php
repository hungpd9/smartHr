<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;

class WorkflowDefinition extends Model
{
    protected $table = 'workflow_definitions';

    protected $fillable = [
        'tenant_id',
        'request_type',
        'name',
        'description',
        'total_levels',
        'steps_config',
        'condition_rules',
        'is_active',
    ];

    protected $casts = [
        'total_levels' => 'integer',
        'steps_config' => 'array',
        'condition_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
