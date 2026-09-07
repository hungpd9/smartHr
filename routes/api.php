<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Licensing\Controllers\AttendanceSyncController;

/*
|--------------------------------------------------------------------------
| Edge Collector IoT Daemon API Routes
|--------------------------------------------------------------------------
| Các endpoint nhận dữ liệu log quẹt thẻ từ Golang Edge Collector Daemon
| bảo vệ bằng chữ ký số HMAC-SHA256 và tệp bản quyền Ed25519 smart-hr.lic.
*/
Route::prefix('v1')->group(function () {
    Route::post('/attendance/sync', [AttendanceSyncController::class, 'sync'])->name('api.v1.attendance.sync');
});
