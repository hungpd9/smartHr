<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    protected $table = 'holidays';

    protected $fillable = [
        'tenant_id',
        'name',
        'holiday_date',
        'year',
        'is_statutory',
        'notes',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'year' => 'integer',
        'is_statutory' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
