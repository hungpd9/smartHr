<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 16. Raw Attendance Logs Table (Bảng log thô bất biến từ máy chấm công)
        Schema::create('raw_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->dateTime('punch_time')->comment('Thời gian quẹt thẻ chính xác');
            $table->enum('punch_state', ['CHECK_IN', 'CHECK_OUT', 'BREAK_OUT', 'BREAK_IN', 'AUTO'])->default('AUTO');
            $table->string('device_sn', 100)->comment('Số serial máy chấm công');
            $table->string('verification_mode', 50)->default('FINGERPRINT')->comment('FACE, FINGERPRINT, CARD, PIN');
            $table->string('raw_payload_hash', 64)->comment('SHA-256 đối soát toàn vẹn gói tin HMAC');
            $table->string('sync_batch_id', 100)->index()->comment('Mã batch đồng bộ từ Edge Daemon');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'punch_time'], 'idx_emp_punch');
            $table->index(['tenant_id', 'punch_time'], 'idx_tenant_punch');
        });

        // 17. Daily Attendances Table (Bảng công chi tiết ngày sau khi qua 7-Pipe)
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('roster_id')->nullable()->constrained('rosters')->nullOnDelete();
            $table->dateTime('actual_in')->nullable();
            $table->dateTime('actual_out')->nullable();
            $table->decimal('standard_hours', 4, 2)->default(0.00)->comment('Số giờ công chuẩn');
            $table->unsignedInteger('late_minutes')->default(0)->comment('Phút đi muộn sau khi trừ miễn trừ');
            $table->unsignedInteger('early_minutes')->default(0)->comment('Phút về sớm sau khi trừ miễn trừ');
            $table->decimal('late_penalty_amount', 15, 2)->default(0.00)->comment('Tiền phạt muộn/sớm');
            $table->decimal('ot_day_hours', 4, 2)->default(0.00)->comment('Giờ OT ban ngày');
            $table->decimal('ot_night_hours', 4, 2)->default(0.00)->comment('Giờ OT ban đêm');
            $table->decimal('ot_weekend_hours', 4, 2)->default(0.00);
            $table->decimal('ot_holiday_hours', 4, 2)->default(0.00);
            $table->enum('status', ['PRESENT', 'LATE_EARLY', 'ABSENT', 'LEAVE_PAID', 'LEAVE_UNPAID', 'HOLIDAY'])->default('PRESENT');
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'uk_emp_work_date');
            $table->index(['tenant_id', 'work_date'], 'idx_tenant_work_date');
        });

        // 18. Monthly Attendances Table (Tổng hợp công tháng & Khóa tháng)
        Schema::create('monthly_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('month_year', 7)->comment('Tháng kỳ công (VD: 2026-09)');
            $table->decimal('total_standard_days', 4, 2)->default(0.00);
            $table->decimal('total_ot_hours', 5, 2)->default(0.00);
            $table->unsignedInteger('total_late_minutes')->default(0);
            $table->decimal('total_penalty_amount', 15, 2)->default(0.00);
            $table->boolean('is_locked')->default(false)->comment('Đã khóa kỳ công (ngăn chặn sửa đổi quá khứ)');
            $table->foreignId('locked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'month_year'], 'uk_emp_month_year');
            $table->index(['tenant_id', 'month_year'], 'idx_tenant_month_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_attendances');
        Schema::dropIfExists('daily_attendances');
        Schema::dropIfExists('raw_attendance_logs');
    }
};
