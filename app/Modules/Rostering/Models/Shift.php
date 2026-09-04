<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\CoreHR\Models\Tenant;

class Shift extends Model
{
    protected $table = 'shifts';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'is_night_shift',
        'is_active',
    ];

    protected $casts = [
        'break_minutes' => 'integer',
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }
}
