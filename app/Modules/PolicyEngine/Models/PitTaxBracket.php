<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PitTaxBracket extends Model
{
    protected $table = 'pit_tax_brackets';

    protected $fillable = [
        'policy_version_id',
        'tier',
        'min_income',
        'max_income',
        'tax_rate',
        'quick_subtraction',
    ];

    protected $casts = [
        'tier' => 'integer',
        'min_income' => 'decimal:2',
        'max_income' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'quick_subtraction' => 'decimal:2',
    ];

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class);
    }
}
