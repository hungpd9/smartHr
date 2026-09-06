<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Services\HelpGuideService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HelpGuideController extends Controller
{
    public function __construct(
        protected HelpGuideService $helpGuideService
    ) {}

    /**
     * Màn hình Trung tâm Hướng dẫn & Trợ giúp Người dùng (Help Center)
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $catalog = $this->helpGuideService->getGuidesForUser($user);

        // Lấy bài viết đang chọn hoặc mặc định là bài đầu tiên trong danh mục
        $selectedRole = $request->query('role');
        $selectedSlug = $request->query('slug');

        $activeGuide = null;

        if ($selectedRole && $selectedSlug) {
            $activeGuide = $this->helpGuideService->getGuideContent($user, $selectedRole, $selectedSlug);
        }

        if (!$activeGuide && !empty($catalog) && !empty($catalog[0]['guides'])) {
            $firstCategory = $catalog[0];
            $firstGuide = $firstCategory['guides'][0];
            $activeGuide = $this->helpGuideService->getGuideContent($user, $firstGuide['role'], $firstGuide['slug']);
        }

        return Inertia::render('Help/Index', [
            'catalog' => $catalog,
            'activeGuide' => $activeGuide,
            'currentRole' => $user->role->value ?? $user->role,
        ]);
    }

    /**
     * API tải nội dung chi tiết bài viết (Hỗ trợ AJAX chuyển bài nhanh)
     */
    public function show(Request $request, string $role, string $slug)
    {
        $user = Auth::user();
        $guide = $this->helpGuideService->getGuideContent($user, $role, $slug);

        if (!$guide) {
            return response()->json([
                'message' => 'Tài liệu không tồn tại hoặc bạn không có quyền truy cập.',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $guide,
        ]);
    }
}
