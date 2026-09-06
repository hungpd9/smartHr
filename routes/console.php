<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lập lịch tự động chốt và gửi bảng công lúc 01:00 sáng hàng ngày
\Illuminate\Support\Facades\Schedule::command('attendance:auto-submit')->dailyAt('01:00');

