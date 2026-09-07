<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\CoreHR\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationChannelConfig extends Model
{
    protected $table = 'notification_channel_configs';

    protected $fillable = [
        'tenant_id',
        'channel',
        'is_active',
        'settings_json',
        'event_subscriptions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings_json' => 'array',
        'event_subscriptions' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }
}
