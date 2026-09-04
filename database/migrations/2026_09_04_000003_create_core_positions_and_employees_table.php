<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 5. Positions Table (Chức vụ / Vị trí)
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50)->comment('Mã chức vụ (VD: DEV_LEAD, HR_MGR, SALES_STAFF)');
            $table->string('name', 150)->comment('Tên chức vụ');
            $table->unsignedSmallInteger('level')->default(1)->comment('Cấp bậc chức danh (phục vụ duyệt hierarchical)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_position_code');
        });

        // 6. Employees Table (Hồ sơ nhân sự)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('user_code', 50)->comment('Mã nhân viên (VD: EMP001)');
            $table->string('full_name', 255);
            $table->enum('gender', ['MALE', 'FEMALE', 'OTHER']);
            $table->date('birth_date');
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->text('encrypted_citizen_id')->nullable()->comment('Số CCCD mã hóa bằng AES-256-GCM dẫn xuất từ License');
            $table->date('join_date')->comment('Ngày chính thức vào công ty');
            $table->boolean('is_pregnant')->default(false)->comment('Đang mang thai (áp dụng Đ.137 Khoản 1 Điểm a BLLĐ)');
            $table->date('pregnancy_start_date')->nullable()->comment('Ngày bắt đầu mang thai');
            $table->date('child_birth_date')->nullable()->comment('Ngày sinh của con nhỏ nhất (áp dụng Đ.137 Khoản 1 Điểm b)');
            $table->enum('status', ['PROBATION', 'OFFICIAL', 'ON_LEAVE', 'TERMINATED'])->default('PROBATION');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_code'], 'uk_tenant_user_code');
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
        });

        // Add foreign key manager_id on department_versions
        Schema::table('department_versions', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('department_versions', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
    }
};
