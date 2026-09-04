<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 3. Departments Table (Danh mục gốc)
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code', 50)->comment('Mã phòng ban cố định (VD: DEPT_SALE, DEPT_IT)');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Phòng ban cấp trên (cấu trúc Tree)');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_dept_code');
            $table->foreign('parent_id')->references('id')->on('departments')->nullOnDelete();
        });

        // 4. Department Versions Table (Lịch sử đổi tên & chức năng nhiệm vụ theo thời gian)
        Schema::create('department_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('name', 255)->comment('Tên phòng ban tại thời kỳ này');
            $table->text('mission_function')->nullable()->comment('Chức năng, nhiệm vụ và quyền hạn');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('Trưởng phòng tại giai đoạn này (sẽ FK tới employees)');
            $table->date('effective_date')->comment('Ngày bắt đầu áp dụng tên/chức năng mới');
            $table->date('end_date')->nullable()->comment('Ngày kết thúc (NULL nếu đang là hiện tại)');
            $table->string('change_reason', 255)->nullable()->comment('Căn cứ đổi tên/tái cơ cấu');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['department_id', 'effective_date', 'end_date'], 'idx_dept_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_versions');
        Schema::dropIfExists('departments');
    }
};
