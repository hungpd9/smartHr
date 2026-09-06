<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Commands;

use App\Modules\Attendance\Services\MonthlyTimesheetLifecycleService;
use App\Modules\CoreHR\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoSubmitTimesheetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:auto-submit
                            {--tenant= : Chỉ định ID tenant cụ thể}
                            {--month= : Tháng cần chốt công (mặc định tháng trước Y-m)}
                            {--force : Bỏ qua kiểm tra ngày auto_submit_day}';

    /**
     * The console command description.
     */
    protected $description = 'Tự động chốt và gửi bảng công tháng lên quản lý duyệt theo cấu hình chu trình Zero-Hardcode';

    public function handle(MonthlyTimesheetLifecycleService $lifecycleService): int
    {
        $targetMonth = $this->option('month') ?: Carbon::today()->subMonth()->format('Y-m');
        $tenantIdOption = $this->option('tenant');
        $force = (bool) $this->option('force');

        $this->info("Bắt đầu tiến trình tự động gửi bảng công tháng: [{$targetMonth}]...");

        $tenantsQuery = Tenant::query();
        if ($tenantIdOption) {
            $tenantsQuery->where('id', (int) $tenantIdOption);
        }
        $tenants = $tenantsQuery->get();

        $today = Carbon::today();
        $totalSubmitted = 0;

        foreach ($tenants as $tenant) {
            $config = $lifecycleService->getPolicyConfig((int) $tenant->id);

            if (!$config->enable_auto_submit_timesheet && !$force) {
                $this->line("Tenant #{$tenant->id} [{$tenant->name}]: Tùy chọn tự động gửi bảng công đang TẮT. Bỏ qua.");
                continue;
            }

            $autoSubmitDay = (int) ($config->auto_submit_day ?? 4);

            // Kiểm tra xem hôm nay có phải là ngày auto-submit không (hoặc có --force)
            if (!$force && $today->day < $autoSubmitDay) {
                $this->line("Tenant #{$tenant->id} [{$tenant->name}]: Chưa tới ngày auto-submit ({$today->day}/{$today->format('m')} < ngày {$autoSubmitDay}). Bỏ qua.");
                continue;
            }

            $submittedCount = $lifecycleService->autoSubmitExpiredTimesheets((int) $tenant->id, $targetMonth);
            $totalSubmitted += $submittedCount;

            $this->info("Tenant #{$tenant->id} [{$tenant->name}]: Đã tự động gửi {$submittedCount} bảng công tháng {$targetMonth} lên Quản lý.");
        }

        $this->info("Hoàn tất tiến trình. Tổng cộng: {$totalSubmitted} bảng công đã được tự động submit.");

        return Command::SUCCESS;
    }
}
