<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContract extends Model
{
    protected $table = 'employee_contracts';

    protected $fillable = [
        'employee_id',
        'contract_number',
        'contract_type',
        'sign_date',
        'start_date',
        'end_date',
        'encrypted_salary_data',
        'status',
    ];

    protected $casts = [
        'sign_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
