<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Employee;

class PayrollRecord extends Model
{
    protected $table = 'payroll_records';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'month_year',
        'gross_salary',
        'tax_exempt_ot_amount',
        'taxable_ot_amount',
        'social_insurance_employee',
        'health_insurance_employee',
        'unemployment_insurance_employee',
        'pit_tax_amount',
        'net_salary',
        'encrypted_salary_details',
        'status',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2',
        'tax_exempt_ot_amount' => 'decimal:2',
        'taxable_ot_amount' => 'decimal:2',
        'social_insurance_employee' => 'decimal:2',
        'health_insurance_employee' => 'decimal:2',
        'unemployment_insurance_employee' => 'decimal:2',
        'pit_tax_amount' => 'decimal:2',
        'net_salary' => 'decimal:2',
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
