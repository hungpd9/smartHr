<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Thêm trạng thái phê duyệt bảng công cá nhân vào monthly_attendances
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('total_penalty_amount')
                ->comment('Quản lý đã phê duyệt bảng công (Điều kiện bắt buộc để tính lương)');
            $table->foreignId('approved_by')->nullable()->after('is_approved')
                ->constrained('employees')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });

        // 2. Tạo bảng quản lý kỳ lương và khóa sổ lương tập trung payroll_periods
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('month_year', 7)->comment('Kỳ lương (VD: 2026-09)');
            $table->foreignId('policy_version_id')->nullable()->constrained('policy_versions')->nullOnDelete()
                ->comment('Phiên bản quy chế đã áp dụng tính cho kỳ lương này');
            $table->enum('status', ['DRAFT', 'CALCULATED', 'APPROVED', 'PAID', 'LOCKED'])->default('DRAFT');
            $table->boolean('is_locked')->default(false)->comment('Khóa sổ kỳ lương, cấm sửa đổi hồi tố');
            $table->foreignId('locked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'month_year'], 'uk_tenant_payroll_period');
            $table->index(['tenant_id', 'status'], 'idx_tenant_period_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['is_approved', 'approved_by', 'approved_at']);
        });
    }
};
