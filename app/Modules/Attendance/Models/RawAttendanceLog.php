<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Employee;

class RawAttendanceLog extends Model
{
    protected $table = 'raw_attendance_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'employee_id',
        'punch_time',
        'punch_state',
        'device_sn',
        'verification_mode',
        'raw_payload_hash',
        'sync_batch_id',
        'created_at',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
