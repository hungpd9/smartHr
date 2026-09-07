<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tạo bảng employee_salaries quản lý mức lương cá nhân theo Effective Dating
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->comment('Lương cơ bản thỏa thuận (VND)');
            $table->decimal('insurance_salary', 15, 2)->nullable()->comment('Lương đóng bảo hiểm (NULL nếu lấy bằng base_salary)');
            $table->enum('contract_type', ['OFFICIAL', 'PROBATION', 'SEASONAL', 'INTERN'])->default('OFFICIAL');
            $table->date('effective_date')->comment('Ngày bắt đầu áp dụng mức lương');
            $table->date('end_date')->nullable()->comment('Ngày kết thúc áp dụng (NULL nếu là mức lương hiện tại)');
            $table->string('notes', 255)->nullable()->comment('Lý do điều chỉnh (kết thúc thử việc, tăng lương định kỳ, bổ nhiệm...)');
            $table->boolean('is_locked')->default(false)->comment('Khóa sửa xóa nếu kỳ lương chứa mốc thời gian này đã chốt');
            $table->timestamps();

            $table->unique(['employee_id', 'effective_date'], 'uk_emp_salary_effective');
            $table->index(['tenant_id', 'employee_id', 'effective_date', 'end_date'], 'idx_emp_salary_date');
        });

        // 2. Bổ sung cột salary_breakdown vào payroll_records để lưu chi tiết phiếu lương
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->json('salary_breakdown')->nullable()->after('encrypted_salary_details')
                ->comment('Chi tiết cấu phần thu nhập, phụ cấp, OT miễn thuế, bảo hiểm và thuế TNCN theo từng bậc');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn('salary_breakdown');
        });

        Schema::dropIfExists('employee_salaries');
    }
};
