<?php

declare(strict_types=1);

namespace App\Modules\Licensing\Commands;

use Illuminate\Console\Command;
use App\Modules\Licensing\Services\LicenseService;

class GenerateLicenseCommand extends Command
{
    protected $signature = 'license:generate 
                            {--domain= : Tên miền được cấp phép (VD: hrm.smarthr.vn)}
                            {--customer= : Tên khách hàng/doanh nghiệp}
                            {--expires= : Hạn sử dụng (YYYY-MM-DD, mặc định 1 năm)}
                            {--key= : Private Key Hex của tác giả}
                            {--output=smart-hr.lic : Tên tệp xuất ra}';

    protected $description = 'Công cụ nội bộ của tác giả để sinh tệp bản quyền Ed25519 duy nhất (smart-hr.lic)';

    public function handle(LicenseService $licenseService): int
    {
        $domain = $this->option('domain') ?? $this->ask('Nhập tên miền hệ thống của khách hàng (VD: hrm.company.vn)');
        $customer = $this->option('customer') ?? $this->ask('Nhập tên công ty/khách hàng');
        $expires = $this->option('expires') ?? date('Y-m-d', strtotime('+1 year'));
        $privateKey = $this->option('key');

        if (!$privateKey) {
            $this->error('Thiếu Private Key của tác giả! Dùng lệnh php artisan license:keypair để sinh cặp khóa.');
            return self::FAILURE;
        }

        $licContent = $licenseService->createLicense($domain, $customer, $expires, $privateKey);
        $outputPath = $this->option('output');

        file_put_contents($outputPath, $licContent);

        $this->info("=================================================");
        $this->info("✓ ĐÃ SINH TỆP BẢN QUYỀN THÀNH CÔNG: {$outputPath}");
        $this->info("  - Tên miền: {$domain}");
        $this->info("  - Khách hàng: {$customer}");
        $this->info("  - Hạn dùng: {$expires}");
        $this->info("=================================================");
        $this->comment("Hãy gửi tệp {$outputPath} này cho khách hàng để đặt vào cả thư mục Go Daemon và Laravel.");

        return self::SUCCESS;
    }
}
