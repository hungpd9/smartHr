<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_export_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('report_type', 50)->comment('TIMESHEET_SUMMARY, PAYROLL_ANALYSIS, HEADCOUNT_TURNOVER, COMPLIANCE_AUDIT, CHILD_WELFARE');
            $table->string('file_format', 20)->default('CSV')->comment('CSV, EXCEL_TSV');
            $table->string('file_name', 255);
            $table->json('filter_params')->nullable()->comment('Các tiêu chí lọc: month, branch, department...');
            $table->unsignedInteger('row_count')->default(0)->comment('Tổng số dòng dữ liệu xuất');
            $table->unsignedInteger('execution_time_ms')->default(0)->comment('Thời gian sinh báo cáo (mili giây)');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'report_type'], 'idx_reh_tenant_report');
            $table->index(['tenant_id', 'created_at'], 'idx_reh_tenant_created');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_export_histories');
    }
};
