<?php

declare(strict_types=1);

namespace App\Modules\Licensing\Commands;

use Illuminate\Console\Command;
use App\Modules\Licensing\Services\LicenseService;

class GenerateKeypairCommand extends Command
{
    protected $signature = 'license:keypair';

    protected $description = 'Sinh cặp khóa Ed25519 mới của tác giả (Private Key giữ bí mật, Public Key nhúng vào Go và Laravel)';

    public function handle(LicenseService $licenseService): int
    {
        $keys = $licenseService->generateKeyPair();

        $this->info("=================================================================");
        $this->info("        CẶP KHÓA BẢN QUYỀN ED25519 (AUTHOR ROOT KEYS)");
        $this->info("=================================================================");
        $this->line("<fg=green>PUBLIC KEY (Nhúng vào Golang & Laravel):</>");
        $this->line($keys['public_key']);
        $this->newLine();
        $this->line("<fg=red>PRIVATE KEY (BÍ MẬT TUYỆT ĐỐI - TÁC GIẢ GIỮ TRÊN MÁY CÁ NHÂN):</>");
        $this->line($keys['private_key']);
        $this->info("=================================================================");
        $this->comment("LƯU Ý: Lưu Private Key vào nơi an toàn để ký file smart-hr.lic khi bán cho khách hàng!");

        return self::SUCCESS;
    }
}
