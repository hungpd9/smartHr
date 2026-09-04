<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $table = 'branches';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'address',
        'is_remote_or_island',
        'secret_key',
        'is_active',
    ];

    protected $casts = [
        'is_remote_or_island' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
