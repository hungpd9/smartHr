<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'is_default',
        'night_allowance_rate',
        'night_bonus_amount',
        'is_active',
    ];

    protected $casts = [
        'break_minutes' => 'integer',
        'is_night_shift' => 'boolean',
        'is_default' => 'boolean',
        'night_allowance_rate' => 'decimal:2',
        'night_bonus_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'work_hours',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Tính toán số giờ làm việc thực tế của ca sau khi trừ giờ nghỉ
     */
    public function getWorkHoursAttribute(): float
    {
        if (!$this->start_time || !$this->end_time) {
            return 8.0;
        }

        $start = strtotime("2000-01-01 {$this->start_time}");
        $end = strtotime("2000-01-01 {$this->end_time}");

        // Ca vắt qua đêm
        if ($end <= $start) {
            $end = strtotime("2000-01-02 {$this->end_time}");
        }

        $durationMinutes = ($end - $start) / 60;
        $netMinutes = max(0, $durationMinutes - ($this->break_minutes ?? 0));

        return round($netMinutes / 60, 2);
    }
}
