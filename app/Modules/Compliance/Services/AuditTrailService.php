<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Services;

use App\Modules\Compliance\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

class AuditTrailService
{
    /**
     * Ghi nhận biến động kiểm toán hệ thống
     */
    public function log(
        int $tenantId,
        string $actionType,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditTrail {
        $user = Auth::user();

        return AuditTrail::create([
            'tenant_id' => $tenantId,
            'actor_id' => $user?->id,
            'actor_type' => $user?->role?->value ?? ($user ? 'USER' : 'SYSTEM'),
            'action_type' => $actionType,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 250),
            'created_at' => Carbon::now(),
        ]);
    }
}
