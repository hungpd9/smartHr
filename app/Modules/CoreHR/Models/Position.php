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
        'department_id',
        'code',
        'name',
        'level',
        'allowance_amount',
        'bonus_one_time',
        'description',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'allowance_amount' => 'decimal:2',
        'bonus_one_time' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function careerTimelines(): HasMany
    {
        return $this->hasMany(EmployeeCareerTimeline::class);
    }
}
