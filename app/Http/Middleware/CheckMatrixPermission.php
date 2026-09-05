<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\CoreHR\Services\PermissionResolutionService;

class CheckMatrixPermission
{
    public function __construct(
        protected PermissionResolutionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $moduleCode
     * @param  string  $permissionType 'view' | 'manage' | 'approve'
     */
    public function handle(Request $request, Closure $next, string $moduleCode, string $permissionType = 'view'): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Yêu cầu đăng nhập để truy cập tài nguyên này.');
        }

        if (!$this->permissionService->canAccess($user, $moduleCode, $permissionType)) {
            abort(403, "Bạn không được cấp quyền [{$permissionType}] đối với phân hệ [{$moduleCode}].");
        }

        return $next($request);
    }
}
