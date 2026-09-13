<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Hiển thị màn hình đăng nhập
     */
    public function showLogin(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        // Lấy danh sách tài khoản demo đại diện: mỗi phân quyền đúng 1 tài khoản
        $rolesOrder = [
            UserRole::SUPER_ADMIN->value,
            UserRole::HR_ADMIN->value,
            UserRole::DEPARTMENT_MANAGER->value,
            UserRole::ACCOUNTANT->value,
            UserRole::EMPLOYEE->value,
        ];

        $demoUsers = User::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'email', 'role'])
            ->unique(fn ($u) => $u->role instanceof UserRole ? $u->role->value : (string) $u->role)
            ->sortBy(function ($u) use ($rolesOrder) {
                $val = $u->role instanceof UserRole ? $u->role->value : (string) $u->role;
                $idx = array_search($val, $rolesOrder, true);
                return $idx !== false ? $idx : 99;
            })
            ->values()
            ->map(function ($u) {
                $roleEnum = $u->role instanceof UserRole ? $u->role : UserRole::from((string) $u->role);
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $roleEnum->value,
                    'role_label' => $roleEnum->label('vi'),
                    'role_desc' => $roleEnum->description('vi'),
                ];
            });

        return Inertia::render('Auth/Login', [
            'demoUsers' => $demoUsers,
        ]);
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email hoặc mã nhân viên',
            'password.required' => 'Vui lòng nhập mật khẩu',
        ]);

        $remember = $request->boolean('remember', false);

        // Hỗ trợ đăng nhập bằng Email hoặc Mã Nhân viên (User Code)
        $user = User::where('email', $credentials['email'])
            ->orWhereHas('employee', function ($q) use ($credentials) {
                $q->where('user_code', $credentials['email']);
            })
            ->first();

        if ($user && Auth::attempt(['id' => $user->id, 'password' => $credentials['password']], $remember)) {
            if (! $user->is_active) {
                Auth::logout();
                return back()->withErrors(['email' => 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động.']);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))->with('success', "Đăng nhập thành công! Chào mừng {$user->name}.");
        }

        return back()->withErrors([
            'email' => 'Thông tin đăng nhập không chính xác hoặc tài khoản không tồn tại.',
        ])->onlyInput('email');
    }

    /**
     * Xử lý đăng xuất
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Đã đăng xuất khỏi hệ thống thành công.');
    }

    /**
     * Chuyển đổi vai trò thử nghiệm nhanh (Hỗ trợ Manual Test cho người dùng)
     */
    public function switchRole(Request $request, string $role): RedirectResponse
    {
        $targetRole = UserRole::tryFrom(strtoupper($role));
        if (! $targetRole) {
            return back()->with('error', 'Vai trò yêu cầu không hợp lệ.');
        }

        $user = User::where('role', $targetRole)->where('is_active', true)->first();
        if (! $user) {
            return back()->with('error', "Không tìm thấy tài khoản mẫu cho vai trò {$targetRole->value}.");
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', "Đã chuyển sang vai trò: {$targetRole->label('vi')} ({$user->name})");
    }

    /**
     * Cập nhật tùy chọn cá nhân (Theme, Ngôn ngữ) vào CSDL
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_preference' => ['nullable', 'string', 'in:light,dark,system'],
            'locale_preference' => ['nullable', 'string', 'in:vi,en'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->update(array_filter($validated));

        return response()->json([
            'success' => true,
            'theme_preference' => $user->theme_preference,
            'locale_preference' => $user->locale_preference,
        ]);
    }
}
