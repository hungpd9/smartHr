<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'code',
        'name',
        'domain',
        'status',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(\App\Modules\LeavePolicy\Models\LeaveBalance::class);
    }

    public function overtimeAccumulations(): HasMany
    {
        return $this->hasMany(\App\Modules\LeavePolicy\Models\OvertimeAccumulation::class);
    }
}
