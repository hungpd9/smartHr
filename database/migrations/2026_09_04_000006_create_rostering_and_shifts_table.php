<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 13. Shifts Table (Ca làm việc mẫu)
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50)->comment('Mã ca (VD: CA_SANG, CA_TOI, CA_DEM, CA_GAY)');
            $table->string('name', 100)->comment('Tên ca làm việc');
            $table->time('start_time')->comment('Giờ bắt đầu (VD: 08:00:00)');
            $table->time('end_time')->comment('Giờ kết thúc (VD: 17:00:00)');
            $table->unsignedSmallInteger('break_minutes')->default(60)->comment('Số phút nghỉ giữa ca');
            $table->boolean('is_night_shift')->default(false)->comment('Ca đêm (22:00 - 06:00)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_shift_code');
        });

        // 14. Rosters Table (Lịch phân ca theo ngày của nhân viên)
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('roster_date')->comment('Ngày phân ca');
            $table->boolean('is_published')->default(false)->comment('Đã công bố cho nhân viên xem hay chưa');
            $table->json('violation_flags')->nullable()->comment('Cảnh báo vi phạm ràng buộc mềm nếu có');
            $table->enum('status', ['SCHEDULED', 'ATTENDED', 'ABSENT', 'SWAPPED'])->default('SCHEDULED');
            $table->timestamps();

            $table->unique(['employee_id', 'roster_date'], 'uk_emp_roster_date');
            $table->index(['tenant_id', 'roster_date'], 'idx_tenant_roster_date');
        });

        // 15. Shift Swap Requests Table (Yêu cầu đổi ca & bắt ca thay)
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('target_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requester_roster_id')->constrained('rosters')->cascadeOnDelete();
            $table->foreignId('target_roster_id')->nullable()->constrained('rosters')->nullOnDelete()->comment('NULL nếu là Cover bắt ca thay');
            $table->enum('swap_type', ['SWAP', 'COVER'])->comment('SWAP: Đổi ca; COVER: Nhận ca thay');
            $table->text('reason')->nullable();
            $table->boolean('is_legal_compliant')->default(true)->comment('Kết quả kiểm tra Solver: Không vi phạm luật 12h/24h');
            $table->json('violation_warnings')->nullable();
            $table->enum('status', ['PENDING_TARGET', 'PENDING_MANAGER', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING_TARGET');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('rosters');
        Schema::dropIfExists('shifts');
    }
};
