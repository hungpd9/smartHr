<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class ApprovalRequest extends Model
{
    protected $table = 'approval_requests';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'request_type',
        'form_payload',
        'is_voluntary_consent',
        'voluntary_agreement_file',
        'voluntary_agreement_url',
        'current_step',
        'snapshot_approvers',
        'status',
    ];

    protected $casts = [
        'form_payload' => 'array',
        'is_voluntary_consent' => 'boolean',
        'current_step' => 'integer',
        'snapshot_approvers' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'request_id')->orderBy('step');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    /**
     * Lấy cấu hình thông tin người duyệt ở bước hiện tại từ snapshot
     */
    public function getCurrentApproverStep(): ?array
    {
        if (empty($this->snapshot_approvers)) {
            return null;
        }

        foreach ($this->snapshot_approvers as $stepConfig) {
            if (($stepConfig['level'] ?? $stepConfig['step'] ?? null) === $this->current_step) {
                return $stepConfig;
            }
        }

        return null;
    }
}
