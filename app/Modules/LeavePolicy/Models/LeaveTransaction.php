<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveTransaction extends Model
{
    protected $table = 'leave_transactions';

    public $timestamps = false;

    protected $fillable = [
        'leave_balance_id',
        'transaction_type',
        'days',
        'reference_id',
        'description',
        'created_at',
    ];

    protected $casts = [
        'days' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }
}
