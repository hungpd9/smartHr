<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Licensing\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware EnsureLicenseActiveMiddleware (Tầng phòng thủ 1 - Application Firewall)
 * Chặn đứng toàn bộ truy cập nghiệp vụ nếu hệ thống chưa kích hoạt hoặc hết hạn bản quyền.
 * Chỉ mở duy nhất các route: /licensing, /login, /logout, /up.
 */
class EnsureLicenseActiveMiddleware
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cho phép vượt qua các route ngoại lệ phục vụ nạp license và xác thực
        if (
            $request->routeIs('licensing.*') ||
            $request->is('licensing*') ||
            $request->routeIs('login') ||
            $request->routeIs('logout') ||
            $request->is('up')
        ) {
            return $next($request);
        }

        $activeLicense = $this->licenseService->getActiveLicense();

        if (! ($activeLicense['is_valid'] ?? false)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'LICENSE_REQUIRED',
                    'message' => 'Giấy phép bản quyền hệ thống chưa được kích hoạt hoặc đã hết hạn. Vui lòng nạp tệp smart-hr.lic hợp lệ.',
                    'status' => $activeLicense['status'] ?? 'INVALID',
                ], 403);
            }

            return redirect()->route('licensing.index')->with(
                'warning',
                'Hệ thống chưa được kích hoạt bản quyền hợp lệ. Vui lòng nạp tệp smart-hr.lic của tác giả để tiếp tục sử dụng.'
            );
        }

        return $next($request);
    }
}
