<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Commands;

use App\Modules\Compliance\Services\ComplianceScannerService;
use App\Modules\CoreHR\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScanComplianceAlertsCommand extends Command
{
    /**
     * Tên và chữ ký của console command
     */
    protected $signature = 'compliance:scan 
                            {--tenant= : ID của Tenant cụ thể cần quét (mặc định quét tất cả)} 
                            {--date= : Ngày cần quét kiểm tra Y-m-d (mặc định hôm nay)}';

    /**
     * Mô tả console command
     */
    protected $description = 'Tự động rà soát toàn diện các vi phạm Bộ luật Lao động 2019 (Trần OT, Nghỉ giữa ca, Thai sản, HĐLĐ, Kiệt sức)';

    public function handle(ComplianceScannerService $scannerService): int
    {
        $tenantId = $this->option('tenant');
        $dateStr = $this->option('date') ?: Carbon::today()->toDateString();

        $this->info("==================================================================");
        $this->info("🔍 BẮT ĐẦU QUÉT TUÂN THỦ PHÁP LUẬT LAO ĐỘNG (BLLĐ 2019)");
        $this->info("📅 Mốc ngày kiểm tra: {$dateStr}");
        $this->info("==================================================================");

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('status', 'ACTIVE')->get();

        if ($tenants->isEmpty()) {
            $this->warn("Không tìm thấy Tenant nào để quét.");
            return Command::SUCCESS;
        }

        $totalAlertsAll = 0;

        foreach ($tenants as $tenant) {
            $this->line("\n🏢 Đang quét Tenant: <comment>{$tenant->name}</comment> (ID: {$tenant->id})...");

            $result = $scannerService->scanAll($tenant->id, $dateStr);

            $this->info("  ✓ Đã hoàn tất quét:");
            $this->line("    - Cảnh báo/Vi phạm OT: <fg=yellow>{$result['details']['overtime_cap_alerts']}</>");
            $this->line("    - Vi phạm khoảng cách 2 ca (<12h): <fg=red>{$result['details']['rest_between_shifts_alerts']}</>");
            $this->line("    - Vi phạm nghỉ hàng tuần (7 ngày): <fg=yellow>{$result['details']['weekly_rest_alerts']}</>");
            $this->line("    - Vi phạm bảo vệ thai sản (Đ.137): <fg=red>{$result['details']['maternity_protection_alerts']}</>");
            $this->line("    - Hợp đồng sắp hết hạn (30 ngày): <fg=cyan>{$result['details']['contract_expiring_alerts']}</>");
            $this->line("    - Nguy cơ kiệt sức (Burnout): <fg=yellow>{$result['details']['burnout_risk_alerts']}</>");
            $this->line("    => Điểm sức khỏe tuân thủ: <fg=green>{$result['stats']['compliance_score']}%</>");

            $totalAlertsAll += $result['total_alerts_generated_or_updated'];
        }

        $this->info("\n==================================================================");
        $this->info("🎉 HOÀN TẤT TOÀN BỘ QUÁ TRÌNH QUÉT TUÂN THỦ");
        $this->info("Tổng số cảnh báo đã ghi nhận/cập nhật: {$totalAlertsAll}");
        $this->info("==================================================================");

        return Command::SUCCESS;
    }
}
