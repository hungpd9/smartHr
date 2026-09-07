<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $table = 'payroll_records';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'month_year',
        'gross_salary',
        'position_allowance_amount',
        'child_allowance_amount',
        'certificate_allowance_amount',
        'tax_exempt_ot_amount',
        'taxable_ot_amount',
        'social_insurance_employee',
        'health_insurance_employee',
        'unemployment_insurance_employee',
        'pit_tax_amount',
        'net_salary',
        'salary_breakdown',
        'encrypted_salary_details',
        'status',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2',
        'position_allowance_amount' => 'decimal:2',
        'child_allowance_amount' => 'decimal:2',
        'certificate_allowance_amount' => 'decimal:2',
        'tax_exempt_ot_amount' => 'decimal:2',
        'taxable_ot_amount' => 'decimal:2',
        'social_insurance_employee' => 'decimal:2',
        'health_insurance_employee' => 'decimal:2',
        'unemployment_insurance_employee' => 'decimal:2',
        'pit_tax_amount' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'salary_breakdown' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
