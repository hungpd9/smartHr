<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Models\User;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_id',
        'type',
        'severity',
        'title',
        'content',
        'action_url',
        'data_payload',
        'is_read',
        'read_at',
        'channels_sent',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'data_payload' => 'array',
        'channels_sent' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
