<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CoreHR\Models\Tenant;

class Allowance extends Model
{
    protected $table = 'allowances';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'apply_type',
        'is_insurance_taxable',
        'is_pit_taxable',
        'is_included_in_ot_wage',
        'tax_exemption_limit',
        'default_amount',
        'visibility',
        'notes',
    ];

    protected $casts = [
        'is_insurance_taxable' => 'boolean',
        'is_pit_taxable' => 'boolean',
        'is_included_in_ot_wage' => 'boolean',
        'tax_exemption_limit' => 'decimal:2',
        'default_amount' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility', 'VISIBLE');
    }
}
