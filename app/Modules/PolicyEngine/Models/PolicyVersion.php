<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Modules\CoreHR\Models\Tenant;

class PolicyVersion extends Model
{
    protected $table = 'policy_versions';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function attendanceConfig(): HasOne
    {
        return $this->hasOne(AttendancePolicyConfig::class);
    }

    public function childWelfarePolicies(): HasMany
    {
        return $this->hasMany(ChildWelfarePolicy::class);
    }

    public function pitTaxBrackets(): HasMany
    {
        return $this->hasMany(PitTaxBracket::class)->orderBy('tier');
    }
}
