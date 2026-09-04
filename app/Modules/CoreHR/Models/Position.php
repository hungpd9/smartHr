<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $table = 'positions';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'level',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function careerTimelines(): HasMany
    {
        return $this->hasMany(EmployeeCareerTimeline::class);
    }
}
