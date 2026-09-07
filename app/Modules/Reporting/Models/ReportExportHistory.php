<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Modules\CoreHR\Models\Tenant;
use App\Models\User;
use App\Modules\Reporting\Enums\ReportType;

class ReportExportHistory extends Model
{
    protected $table = 'report_export_histories';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'report_type',
        'file_format',
        'file_name',
        'filter_params',
        'row_count',
        'execution_time_ms',
        'ip_address',
    ];

    protected $casts = [
        'filter_params' => 'array',
        'row_count' => 'integer',
        'execution_time_ms' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getReportTypeLabelAttribute(): string
    {
        $enum = ReportType::tryFrom($this->report_type);
        return $enum ? $enum->label() : $this->report_type;
    }
}
