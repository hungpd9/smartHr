<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'default_shift_id',
        'code',
        'parent_id',
        'level',
        'order',
        'status',
    ];

    protected $casts = [
        'level' => 'integer',
        'order' => 'integer',
    ];

    protected $appends = [
        'name',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function defaultShift(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Rostering\Models\Shift::class, 'default_shift_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('order')->orderBy('id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DepartmentVersion::class);
    }

    /**
     * Lấy phiên bản hiện tại đang áp dụng (Effective Dating)
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DepartmentVersion::class)
            ->where('effective_date', '<=', Carbon::today())
            ->where(function ($query) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', Carbon::today());
            })
            ->latest('effective_date');
    }

    public function getNameAttribute(): string
    {
        return $this->currentVersion?->name ?? $this->code;
    }

    /**
     * Truy vấn phiên bản tại một thời điểm lịch sử cụ thể
     */
    public function versionAt(Carbon $date): ?DepartmentVersion
    {
        return $this->versions()
            ->where('effective_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $date);
            })
            ->first();
    }
}
