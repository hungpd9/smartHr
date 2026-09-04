<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Employee;

class ApprovalHistory extends Model
{
    protected $table = 'approval_histories';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'approver_id',
        'step',
        'action',
        'comment',
        'verified_child_under_12m_consent',
        'created_at',
    ];

    protected $casts = [
        'step' => 'integer',
        'verified_child_under_12m_consent' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}
