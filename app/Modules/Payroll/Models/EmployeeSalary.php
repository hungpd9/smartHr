<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    protected $table = 'employee_salaries';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'base_salary',
        'insurance_salary',
        'contract_type',
        'effective_date',
        'end_date',
        'notes',
        'is_locked',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'insurance_salary' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_locked' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope lấy mức lương có hiệu lực tại một thời điểm
     */
    public function scopeEffectiveAt(Builder $query, Carbon|string $date): Builder
    {
        $targetDate = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();

        return $query->whereDate('effective_date', '<=', $targetDate)
            ->where(function (Builder $sub) use ($targetDate) {
                $sub->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $targetDate);
            })
            ->latest('effective_date');
    }
}
