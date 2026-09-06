<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Models\AuditTrail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuditTrailController extends Controller
{
    /**
     * Tra cứu nhật ký kiểm toán biến động dữ liệu theo đối tượng
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 1);
        $auditableType = $request->input('auditable_type');
        $auditableId = $request->input('auditable_id');
        $limit = min((int) $request->input('limit', 25), 100);

        $query = AuditTrail::where('tenant_id', $tenantId);

        if ($auditableType) {
            // Hỗ trợ cả tên class đầy đủ hoặc tên ngắn (VD: 'Department' -> '%Department%')
            if (str_contains($auditableType, '\\')) {
                $query->where('auditable_type', $auditableType);
            } else {
                $query->where('auditable_type', 'like', "%{$auditableType}");
            }
        }

        if ($auditableId) {
            $query->where('auditable_id', (int) $auditableId);
        }

        $logs = $query->orderByDesc('created_at')->limit($limit)->get();

        // Nạp thông tin người thực hiện
        $actorIds = $logs->pluck('actor_id')->filter()->unique();
        $users = User::whereIn('id', $actorIds)->get()->keyBy('id');

        $result = $logs->map(function ($log) use ($users) {
            $user = $users->get($log->actor_id);
            return [
                'id' => $log->id,
                'action_type' => $log->action_type,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'actor_id' => $log->actor_id,
                'actor_name' => $user?->name ?? 'Hệ thống / Ẩn danh',
                'actor_role' => $log->actor_type,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                'time_ago' => $log->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
