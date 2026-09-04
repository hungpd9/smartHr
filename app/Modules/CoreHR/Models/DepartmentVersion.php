<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentVersion extends Model
{
    protected $table = 'department_versions';

    public $timestamps = false;

    protected $fillable = [
        'department_id',
        'name',
        'mission_function',
        'manager_id',
        'effective_date',
        'end_date',
        'change_reason',
        'created_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
