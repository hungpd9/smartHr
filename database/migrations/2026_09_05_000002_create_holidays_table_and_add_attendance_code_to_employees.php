<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tạo bảng holidays (Danh mục ngày nghỉ lễ, tết theo Điều 112 BLLĐ 2019)
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150)->comment('Tên ngày nghỉ lễ (Tết Dương lịch, Giỗ Tổ, 30/4...)');
            $table->date('holiday_date')->comment('Ngày diễn ra nghỉ lễ');
            $table->unsignedSmallInteger('year')->comment('Năm áp dụng');
            $table->boolean('is_statutory')->default(true)->comment('TRUE nếu là 11 ngày lễ bắt buộc theo Điều 112 BLLĐ 2019');
            $table->text('notes')->nullable()->comment('Ghi chú lịch nghỉ bù, hoán đổi ngày làm việc');
            $table->timestamps();

            $table->unique(['tenant_id', 'holiday_date'], 'uk_tenant_holiday_date');
            $table->index(['tenant_id', 'year'], 'idx_tenant_holiday_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
