<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        // Super Admin luôn có quyền truy cập tất cả
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $userRoleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role;

        if (! in_array($userRoleValue, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Bạn không có thẩm quyền truy cập chức năng này.'], 403);
            }

            abort(403, 'Bạn không có thẩm quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}
