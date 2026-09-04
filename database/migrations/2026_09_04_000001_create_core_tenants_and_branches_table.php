<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tenants Table
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Mã định danh doanh nghiệp (VD: VINGROUP, FPT)');
            $table->string('name', 255)->comment('Tên pháp nhân công ty');
            $table->string('domain', 255)->unique()->comment('Tên miền đăng ký bản quyền (VD: hrm.vingroup.vn)');
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'EXPIRED'])->default('ACTIVE');
            $table->timestamps();
        });

        // 2. Branches Table (Chi nhánh / Cửa hàng / Kho)
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50)->comment('Mã chi nhánh (VD: HN_FLAGSHIP, PQ_STORE)');
            $table->string('name', 255)->comment('Tên chi nhánh/kho');
            $table->string('address', 255)->nullable();
            $table->boolean('is_remote_or_island')->default(false)->comment('Chi nhánh vùng cao, vùng sâu, vùng xa, biên giới, hải đảo (áp dụng Đ.137 Khoản 1 Điểm a BLLĐ)');
            $table->string('secret_key', 100)->comment('Secret key dùng để ký HMAC cho Edge Daemon');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_branch_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
