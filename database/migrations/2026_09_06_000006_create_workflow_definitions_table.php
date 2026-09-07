<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('request_type', 50)->comment('LEAVE, OVERTIME, NIGHT_SHIFT, TIMESHEET_EDIT, SHIFT_SWAP, RESIGNATION, REMOTE_WORK');
            $table->string('name', 255)->comment('Tên quy trình phê duyệt');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('total_levels')->default(1)->comment('Số cấp duyệt (1, 2 hoặc 3 cấp)');
            $table->json('steps_config')->comment('Cấu hình vai trò từng cấp duyệt: DIRECT_MANAGER, DEPARTMENT_HEAD, HR_ADMIN, GENERAL_DIRECTOR');
            $table->json('condition_rules')->nullable()->comment('Quy tắc điều kiện số ngày/số giờ kích hoạt cấp duyệt');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'request_type'], 'uk_tenant_request_type');
            $table->index(['tenant_id', 'is_active'], 'idx_tenant_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_definitions');
    }
};
