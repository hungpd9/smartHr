<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\AuthController;
use App\Modules\CoreHR\Controllers\EmployeeController;
use App\Modules\CoreHR\Controllers\DepartmentController;
use App\Modules\CoreHR\Controllers\BranchController;
use App\Modules\CoreHR\Controllers\AccessControlController;
use App\Modules\PolicyEngine\Controllers\PolicyController;

// --- Guest / Authentication Routes ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// --- Authenticated App Routes ---
Route::middleware('auth')->group(function () {
    // 1. Dashboard trung tâm
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // 2. Tiện ích: Cập nhật tùy chọn cá nhân & Chuyển đổi vai trò kiểm thử
    Route::patch('/user/preferences', [AuthController::class, 'updatePreferences'])->name('user.preferences');
    Route::get('/auth/switch-role/{role}', [AuthController::class, 'switchRole'])->name('auth.switch-role');

    // 3. Phân hệ 1: Core HR & Career Timeline (Bảo vệ bởi Ma trận Phân quyền CORE_HR)
    Route::prefix('core-hr')->name('core-hr.')->middleware('perm:CORE_HR,view')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/employees/{id}', [EmployeeController::class, 'show'])->name('employees.show');

        Route::middleware('perm:CORE_HR,manage')->group(function () {
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::put('/employees/{id}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::post('/employees/{id}/offboard', [EmployeeController::class, 'offboard'])->name('employees.offboard');
            Route::post('/employees/{id}/timeline', [EmployeeController::class, 'addTimelineEvent'])->name('employees.timeline');

            Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::post('/departments/{id}/version', [DepartmentController::class, 'updateVersion'])->name('departments.version');

            Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        });
    });

    // 3b. Phân hệ Quản lý Con nhỏ & Chế độ Phúc lợi Gia đình
    Route::prefix('employee-children')->name('employee-children.')->middleware('perm:CORE_HR,view')->group(function () {
        Route::get('/', [\App\Modules\CoreHR\Controllers\EmployeeChildController::class, 'index'])->name('index');

        Route::middleware('perm:CORE_HR,manage')->group(function () {
            Route::post('/', [\App\Modules\CoreHR\Controllers\EmployeeChildController::class, 'store'])->name('store');
            Route::put('/{id}', [\App\Modules\CoreHR\Controllers\EmployeeChildController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Modules\CoreHR\Controllers\EmployeeChildController::class, 'destroy'])->name('destroy');
        });
    });

    // 4. Phân hệ 2: Centralized Policy Engine (Trung tâm Cấu hình Quy chế Động - Zero-Hardcode)
    Route::prefix('policies')->name('policies.')->middleware('perm:POLICY_WELFARE,view')->group(function () {
        // Màn hình xem quy chế & sandbox tính thử nghiệm
        Route::get('/', [PolicyController::class, 'index'])->name('index');
        Route::post('/test-calculation', [PolicyController::class, 'testCalculation'])->name('test-calculation');

        // Cập nhật cấu hình lương (Bảo vệ bởi PAYROLL_SALARY,manage)
        Route::put('/payroll-config/{versionId}', [PolicyController::class, 'updatePayrollConfig'])
            ->name('payroll-config.update')
            ->middleware('perm:PAYROLL_SALARY,manage');

        // Quản lý Danh mục Phụ cấp (Bảo vệ bởi PAYROLL_SALARY,manage)
        Route::middleware('perm:PAYROLL_SALARY,manage')->group(function () {
            Route::post('/allowances', [PolicyController::class, 'storeAllowance'])->name('allowances.store');
            Route::patch('/allowances/{id}/toggle', [PolicyController::class, 'toggleAllowanceVisibility'])->name('allowances.toggle');
            Route::delete('/allowances/{id}', [PolicyController::class, 'destroyAllowance'])->name('allowances.destroy');
        });

        // Các hành động sửa đổi / cấu hình quy chế chấm công & chế độ phúc lợi (Bảo vệ bởi POLICY_WELFARE,manage)
        Route::middleware('perm:POLICY_WELFARE,manage')->group(function () {
            Route::post('/versions', [PolicyController::class, 'storeVersion'])->name('versions.store');
            Route::put('/attendance-config/{versionId}', [PolicyController::class, 'updateAttendanceConfig'])->name('attendance-config.update');
            Route::post('/child-welfare/{versionId}', [PolicyController::class, 'storeChildWelfarePolicy'])->name('child-welfare.store');
            Route::patch('/child-welfare/{id}/toggle', [PolicyController::class, 'toggleChildWelfarePolicy'])->name('child-welfare.toggle');

            // Quản lý Chế độ Phúc lợi Doanh nghiệp Đa năng
            Route::post('/welfare-schemes', [PolicyController::class, 'storeWelfareScheme'])->name('welfare-schemes.store');
            Route::patch('/welfare-schemes/{id}/toggle', [PolicyController::class, 'toggleWelfareSchemeVisibility'])->name('welfare-schemes.toggle');
            Route::delete('/welfare-schemes/{id}', [PolicyController::class, 'destroyWelfareScheme'])->name('welfare-schemes.destroy');
        });
    });

    // 4b. Phân hệ Ma Trận Phân Quyền & Ủy Quyền Hệ Thống (Chỉ dành cho SUPER_ADMIN)
    Route::prefix('access-control')->name('access-control.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [AccessControlController::class, 'index'])->name('index');
        Route::post('/update', [AccessControlController::class, 'update'])->name('update');
    });

    // 5. Phân hệ 3: Time & Attendance Engine (Động cơ Chấm công Thông minh, Pipeline 7-Pipe & Timesheet Preview)
    Route::prefix('attendance')->name('attendance.')->middleware('perm:ATTENDANCE,view')->group(function () {
        Route::get('/', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'index'])->name('index');
        Route::get('/raw-logs', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'rawLogs'])->name('raw-logs');

        Route::middleware('perm:ATTENDANCE,manage')->group(function () {
            Route::post('/calculate', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'calculate'])->name('calculate');
        });

        Route::middleware('perm:ATTENDANCE,approve')->group(function () {
            Route::post('/lock-month', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'lockMonth'])->name('lock-month');
        });
    });
});

