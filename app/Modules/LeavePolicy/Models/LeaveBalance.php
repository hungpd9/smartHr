<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class LeaveBalance extends Model
{
    protected $table = 'leave_balances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'year',
        'leave_type',
        'carried_over_days',
        'carried_over_expiry_date',
        'statutory_allocated_days',
        'seniority_bonus_days',
        'welfare_bonus_days',
        'total_entitled_days',
        'accrued_days_to_date',
        'used_days',
        'pending_days',
        'remaining_days',
        'annual_entitlement',
        'carried_forward_days',
        'comp_off_hours',
        'taken_paid_days',
        'taken_unpaid_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'carried_over_days' => 'decimal:2',
        'carried_over_expiry_date' => 'date',
        'statutory_allocated_days' => 'decimal:2',
        'seniority_bonus_days' => 'decimal:2',
        'welfare_bonus_days' => 'decimal:2',
        'total_entitled_days' => 'decimal:2',
        'accrued_days_to_date' => 'decimal:2',
        'used_days' => 'decimal:2',
        'pending_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
        'annual_entitlement' => 'decimal:2',
        'carried_forward_days' => 'decimal:2',
        'comp_off_hours' => 'decimal:2',
        'taken_paid_days' => 'decimal:2',
        'taken_unpaid_days' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class, 'leave_balance_id')->latest('created_at');
    }

    /**
     * Tự động đồng bộ và tính toán lại tổng số ngày phép khả dụng
     */
    public function recalculateTotals(): void
    {
        $this->total_entitled_days = (float) $this->statutory_allocated_days 
            + (float) $this->seniority_bonus_days 
            + (float) $this->welfare_bonus_days;

        $this->remaining_days = max(
            0.0,
            (float) $this->total_entitled_days 
            + (float) $this->carried_over_days 
            - (float) $this->used_days 
            - (float) $this->pending_days
        );

        // Đồng bộ các trường backward-compatibility
        $this->annual_entitlement = $this->total_entitled_days;
        $this->carried_forward_days = $this->carried_over_days;
        $this->taken_paid_days = $this->used_days;

        $this->save();
    }
}
