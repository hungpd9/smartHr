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
            Route::put('/departments/{id}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('/departments/{id}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
            Route::post('/departments/{id}/version', [DepartmentController::class, 'updateVersion'])->name('departments.version');

            Route::post('/positions', [\App\Modules\CoreHR\Controllers\PositionController::class, 'store'])->name('positions.store');
            Route::put('/positions/{id}', [\App\Modules\CoreHR\Controllers\PositionController::class, 'update'])->name('positions.update');
            Route::delete('/positions/{id}', [\App\Modules\CoreHR\Controllers\PositionController::class, 'destroy'])->name('positions.destroy');

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

        // Quản lý Biểu thuế TNCN Động (Bảo vệ bởi PAYROLL_SALARY,manage)
        Route::middleware('perm:PAYROLL_SALARY,manage')->group(function () {
            Route::post('/pit-tax-brackets/{versionId}', [PolicyController::class, 'storePitTaxBracket'])->name('pit-tax-brackets.store');
            Route::put('/pit-tax-brackets/{id}', [PolicyController::class, 'updatePitTaxBracket'])->name('pit-tax-brackets.update');
            Route::delete('/pit-tax-brackets/{id}', [PolicyController::class, 'destroyPitTaxBracket'])->name('pit-tax-brackets.destroy');
            Route::post('/pit-tax-brackets/{versionId}/reset-default', [PolicyController::class, 'resetDefaultPitTaxBrackets'])->name('pit-tax-brackets.reset-default');

            Route::post('/allowances', [PolicyController::class, 'storeAllowance'])->name('allowances.store');
            Route::put('/allowances/{id}', [PolicyController::class, 'updateAllowance'])->name('allowances.update');
            Route::patch('/allowances/{id}/toggle', [PolicyController::class, 'toggleAllowanceVisibility'])->name('allowances.toggle');
            Route::delete('/allowances/{id}', [PolicyController::class, 'destroyAllowance'])->name('allowances.destroy');
        });

        // Các hành động sửa đổi / cấu hình quy chế chấm công & chế độ phúc lợi (Bảo vệ bởi POLICY_WELFARE,manage)
        Route::middleware('perm:POLICY_WELFARE,manage')->group(function () {
            Route::post('/versions', [PolicyController::class, 'storeVersion'])->name('versions.store');
            Route::put('/attendance-config/{versionId}', [PolicyController::class, 'updateAttendanceConfig'])->name('attendance-config.update');

            // Quản lý Phúc lợi con nhỏ (CRUD đầy đủ)
            Route::post('/child-welfare/{versionId}', [PolicyController::class, 'storeChildWelfarePolicy'])->name('child-welfare.store');
            Route::put('/child-welfare/{id}', [PolicyController::class, 'updateChildWelfarePolicy'])->name('child-welfare.update');
            Route::patch('/child-welfare/{id}/toggle', [PolicyController::class, 'toggleChildWelfarePolicy'])->name('child-welfare.toggle');
            Route::delete('/child-welfare/{id}', [PolicyController::class, 'destroyChildWelfarePolicy'])->name('child-welfare.destroy');

            // Quản lý Chế độ Phúc lợi Doanh nghiệp Đa năng (CRUD đầy đủ)
            Route::post('/welfare-schemes', [PolicyController::class, 'storeWelfareScheme'])->name('welfare-schemes.store');
            Route::put('/welfare-schemes/{id}', [PolicyController::class, 'updateWelfareScheme'])->name('welfare-schemes.update');
            Route::patch('/welfare-schemes/{id}/toggle', [PolicyController::class, 'toggleWelfareSchemeVisibility'])->name('welfare-schemes.toggle');
            Route::delete('/welfare-schemes/{id}', [PolicyController::class, 'destroyWelfareScheme'])->name('welfare-schemes.destroy');
        });
    });

    // 4b. Tra cứu Nhật ký Kiểm toán Biến động (Audit Trail API)
    Route::get('/audit-trails', [\App\Modules\Compliance\Controllers\AuditTrailController::class, 'index'])->name('audit-trails.index');

    // 4c. Phân hệ Ma Trận Phân Quyền & Ủy Quyền Hệ Thống (Chỉ dành cho SUPER_ADMIN)
    Route::prefix('access-control')->name('access-control.')->middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/', [AccessControlController::class, 'index'])->name('index');
        Route::post('/update', [AccessControlController::class, 'update'])->name('update');
    });

    // 5. Phân hệ 3: Time & Attendance Engine (Động cơ Chấm công Thông minh, Pipeline 7-Pipe & Timesheet Preview)
    Route::prefix('attendance')->name('attendance.')->middleware('perm:ATTENDANCE,view')->group(function () {
        Route::get('/', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'index'])->name('index');
        Route::get('/raw-logs', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'rawLogs'])->name('raw-logs');
        Route::post('/submit', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'submitTimesheet'])->name('submit');

        Route::middleware('perm:ATTENDANCE,manage')->group(function () {
            Route::post('/calculate', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'calculate'])->name('calculate');
        });

        Route::middleware('perm:ATTENDANCE,approve')->group(function () {
            Route::post('/lock-month', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'lockMonth'])->name('lock-month');
            Route::post('/approve/{id}', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'approveTimesheet'])->name('approve');
            Route::post('/reject/{id}', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'rejectTimesheet'])->name('reject');
            Route::post('/bulk-approve', [\App\Modules\Attendance\Controllers\AttendanceController::class, 'bulkApprove'])->name('bulk-approve');
        });
    });

    // 6. Phân hệ 4: Shift Planning & Rostering (Quản lý Ca làm việc, Ma trận Phân ca & Đổi ca)
    Route::prefix('rosters')->name('rosters.')->middleware('perm:ATTENDANCE,view')->group(function () {
        // Màn hình Ma trận phân ca
        Route::get('/', [\App\Modules\Rostering\Controllers\RosterController::class, 'index'])->name('index');

        // Quản lý Ca làm việc (Shifts)
        Route::get('/shifts', [\App\Modules\Rostering\Controllers\ShiftController::class, 'index'])->name('shifts.index');
        Route::middleware('perm:ATTENDANCE,manage')->group(function () {
            Route::post('/shifts', [\App\Modules\Rostering\Controllers\ShiftController::class, 'store'])->name('shifts.store');
            Route::put('/shifts/{shift}', [\App\Modules\Rostering\Controllers\ShiftController::class, 'update'])->name('shifts.update');
            Route::post('/shifts/{shift}/set-default', [\App\Modules\Rostering\Controllers\ShiftController::class, 'setDefault'])->name('shifts.set-default');
            Route::delete('/shifts/{shift}', [\App\Modules\Rostering\Controllers\ShiftController::class, 'destroy'])->name('shifts.destroy');

            Route::post('/assign', [\App\Modules\Rostering\Controllers\RosterController::class, 'assign'])->name('assign');
            Route::post('/auto-generate', [\App\Modules\Rostering\Controllers\RosterController::class, 'autoGenerate'])->name('auto-generate');
            Route::post('/simulate-auto-generate', [\App\Modules\Rostering\Controllers\RosterController::class, 'simulateAutoGenerate'])->name('simulate-auto-generate');
        });

        Route::middleware('perm:ATTENDANCE,approve')->group(function () {
            Route::post('/publish', [\App\Modules\Rostering\Controllers\RosterController::class, 'publish'])->name('publish');
        });

        // Yêu cầu Đổi ca (Shift Swap & Cover)
        Route::prefix('swaps')->name('swaps.')->group(function () {
            Route::get('/', [\App\Modules\Rostering\Controllers\ShiftSwapController::class, 'index'])->name('index');
            Route::post('/', [\App\Modules\Rostering\Controllers\ShiftSwapController::class, 'store'])->name('store');
            Route::middleware('perm:ATTENDANCE,approve')->group(function () {
                Route::post('/{swapRequest}/approve', [\App\Modules\Rostering\Controllers\ShiftSwapController::class, 'approve'])->name('approve');
                Route::post('/{swapRequest}/reject', [\App\Modules\Rostering\Controllers\ShiftSwapController::class, 'reject'])->name('reject');
            });
        });
    });

    // 7. Phân hệ 5: Leave & Overtime Policy (Quản trị Nghỉ phép, Làm thêm giờ & Phúc lợi Nghỉ ngơi)
    Route::prefix('leave')->name('leave.')->middleware('perm:ATTENDANCE,view')->group(function () {
        Route::get('/balances', [\App\Modules\LeavePolicy\Controllers\LeaveController::class, 'index'])->name('balances');
        Route::get('/ot-accumulations', [\App\Modules\LeavePolicy\Controllers\LeaveController::class, 'otAccumulations'])->name('ot-accumulations');

        Route::middleware('perm:ATTENDANCE,manage')->group(function () {
            Route::post('/adjust', [\App\Modules\LeavePolicy\Controllers\LeaveController::class, 'adjust'])->name('adjust');
            Route::post('/recalculate', [\App\Modules\LeavePolicy\Controllers\LeaveController::class, 'recalculate'])->name('recalculate');
            Route::post('/convert-ot', [\App\Modules\LeavePolicy\Controllers\LeaveController::class, 'convertOt'])->name('convert-ot');
            Route::post('/sync-ot', [\App\Modules\LeavePolicy\Controllers\LeaveController::class, 'syncOt'])->name('sync-ot');
        });
    });

    // Tiện ích alias route /leaves điều hướng về /leave/balances
    Route::get('/leaves', fn() => redirect()->route('leave.balances'))->name('leaves');

    // 8. Phân hệ 6: Payroll Engine (Động cơ Tính Lương, Thuế TNCN 5 Bậc & Khóa Sổ Bất Biến)
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [\App\Modules\Payroll\Controllers\PayrollController::class, 'index'])->name('index');
        Route::post('/calculate', [\App\Modules\Payroll\Controllers\PayrollController::class, 'calculate'])->name('calculate');
        Route::post('/lock-period', [\App\Modules\Payroll\Controllers\PayrollController::class, 'lockPeriod'])->name('lock-period');
        Route::post('/approve-timesheet/{employeeId}', [\App\Modules\Payroll\Controllers\PayrollController::class, 'approveTimesheet'])->name('approve-timesheet');
        Route::get('/records/{id}/payslip', [\App\Modules\Payroll\Controllers\PayrollController::class, 'payslip'])->name('payslip');

        // Quản lý mức lương thỏa thuận cá nhân (Employee Salaries)
        Route::post('/salaries', [\App\Modules\Payroll\Controllers\PayrollController::class, 'storeSalary'])->name('salaries.store');
        Route::put('/salaries/{id}', [\App\Modules\Payroll\Controllers\PayrollController::class, 'updateSalary'])->name('salaries.update');
        Route::delete('/salaries/{id}', [\App\Modules\Payroll\Controllers\PayrollController::class, 'destroySalary'])->name('salaries.destroy');
    });

    // 9. Phân hệ Trung tâm Hướng dẫn Sử dụng Đa tầng (Role-Based Help Center)
    Route::prefix('help')->name('help.')->group(function () {
        Route::get('/', [\App\Modules\Compliance\Controllers\HelpGuideController::class, 'index'])->name('index');
        Route::get('/{role}/{slug}', [\App\Modules\Compliance\Controllers\HelpGuideController::class, 'show'])->name('show');
    });

    // 10. Phân hệ Workflow Engine (Quản trị Luồng Đơn Từ & Phê Duyệt Đa Cấp)
    Route::prefix('workflow')->name('workflow.')->group(function () {
        Route::get('/requests', [\App\Modules\Workflow\Controllers\ApprovalRequestController::class, 'index'])->name('requests.index');
        Route::post('/requests', [\App\Modules\Workflow\Controllers\ApprovalRequestController::class, 'store'])->name('requests.store');
        Route::post('/requests/{id}/approve', [\App\Modules\Workflow\Controllers\ApprovalRequestController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{id}/reject', [\App\Modules\Workflow\Controllers\ApprovalRequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{id}/cancel', [\App\Modules\Workflow\Controllers\ApprovalRequestController::class, 'cancel'])->name('requests.cancel');
        Route::get('/preview-chain', [\App\Modules\Workflow\Controllers\ApprovalRequestController::class, 'previewChain'])->name('requests.preview-chain');

        // Cấu hình quy trình phê duyệt đa cấp
        Route::post('/configs', [\App\Modules\Workflow\Controllers\WorkflowConfigController::class, 'update'])->name('configs.update');
    });

    // 11. Phân hệ Compliance & Early Warning (Trung tâm Giám sát Tuân thủ & Cảnh báo Sớm BLLĐ)
    Route::prefix('compliance')->name('compliance.')->group(function () {
        Route::get('/', [\App\Modules\Compliance\Controllers\ComplianceController::class, 'index'])->name('index');
        Route::post('/scan', [\App\Modules\Compliance\Controllers\ComplianceController::class, 'scanNow'])->name('scan');
        Route::post('/alerts/{id}/resolve', [\App\Modules\Compliance\Controllers\ComplianceController::class, 'resolve'])->name('resolve');
        Route::post('/alerts/bulk-resolve', [\App\Modules\Compliance\Controllers\ComplianceController::class, 'bulkResolve'])->name('bulk-resolve');
    });

    // 12. Phân hệ Notification & Multi-Channel Engine (Thông Báo Đa Kênh & In-App Bell)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Modules\Notification\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [\App\Modules\Notification\Controllers\NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [\App\Modules\Notification\Controllers\NotificationController::class, 'markAllRead'])->name('read-all');
        Route::delete('/{id}', [\App\Modules\Notification\Controllers\NotificationController::class, 'destroy'])->name('destroy');
        Route::post('/channels/save', [\App\Modules\Notification\Controllers\NotificationController::class, 'saveChannelConfig'])->name('channels.save');
        Route::post('/channels/test', [\App\Modules\Notification\Controllers\NotificationController::class, 'testChannel'])->name('channels.test');
    });

    // 13. Phân hệ Reporting & High-Performance Streaming Export (Trung Tâm Báo Cáo Phân Tích & Xuất Dữ Liệu)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Modules\Reporting\Controllers\ReportController::class, 'index'])->name('index');
        Route::get('/preview', [\App\Modules\Reporting\Controllers\ReportController::class, 'preview'])->name('preview');
        Route::get('/export', [\App\Modules\Reporting\Controllers\ReportController::class, 'export'])->name('export');
        Route::get('/history', [\App\Modules\Reporting\Controllers\ReportController::class, 'history'])->name('history');
    });

    // 14. Phân hệ Licensing & Cryptographic Data Anchoring (Bản quyền Ed25519 & Móc neo Mật mã)
    Route::prefix('licensing')->name('licensing.')->group(function () {
        Route::get('/', [\App\Modules\Licensing\Controllers\LicensingController::class, 'index'])->name('index');
        Route::post('/upload', [\App\Modules\Licensing\Controllers\LicensingController::class, 'upload'])->name('upload');
        Route::post('/test-crypto', [\App\Modules\Licensing\Controllers\LicensingController::class, 'testCrypto'])->name('test-crypto');
        Route::get('/sample-config', [\App\Modules\Licensing\Controllers\LicensingController::class, 'downloadSampleConfig'])->name('sample-config');
    });

    // Alias tiện ích
    Route::get('/workflow', fn() => redirect()->route('workflow.requests.index'))->name('workflow');
    Route::get('/requests', fn() => redirect()->route('workflow.requests.index'))->name('requests');
});

