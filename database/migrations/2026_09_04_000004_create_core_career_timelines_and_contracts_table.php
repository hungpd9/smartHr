<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 7. Employee Career Timelines Table (Cây Timeline sự kiện nhân sự - Effective Dating)
        Schema::create('employee_career_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('event_type', [
                'ONBOARDING',
                'TRANSFER',
                'PROMOTION',
                'ROLE_CHANGE',
                'SALARY_ADJUSTMENT',
                'OFFBOARDING'
            ]);
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->string('job_title', 150)->comment('Tên chức danh hiển thị');
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('effective_date')->comment('Ngày bắt đầu có hiệu lực');
            $table->date('end_date')->nullable()->comment('Ngày kết thúc (NULL nếu đang áp dụng)');
            $table->string('decision_number', 100)->nullable()->comment('Số quyết định bổ nhiệm/điều chuyển');
            $table->date('decision_date')->nullable()->comment('Ngày ký quyết định');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date', 'end_date'], 'idx_emp_timeline');
            $table->index(['department_id', 'effective_date'], 'idx_dept_timeline');
        });

        // 8. Employee Contracts Table (Hợp đồng lao động)
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_number', 100)->unique();
            $table->enum('contract_type', [
                'PROBATION',
                'DEFINITE_TERM',
                'INDEFINITE_TERM',
                'SEASONAL'
            ]);
            $table->date('sign_date');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('NULL nếu là không xác định thời hạn');
            $table->text('encrypted_salary_data')->nullable()->comment('Lương cơ bản & phụ cấp mã hóa AES-256-GCM');
            $table->enum('status', ['DRAFT', 'ACTIVE', 'EXPIRED', 'TERMINATED'])->default('ACTIVE');
            $table->timestamps();

            $table->index(['employee_id', 'status'], 'idx_emp_contract_status');
            $table->index(['end_date', 'status'], 'idx_contract_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_career_timelines');
    }
};
