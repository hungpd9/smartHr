<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Nâng cấp bảng leave_balances để hỗ trợ chi tiết quy chuẩn Module 05
        Schema::table('leave_balances', function (Blueprint $table) {
            // Thêm index độc lập cho employee_id để foreign key không phụ thuộc vào unique index uk_emp_leave_year
            $table->index('employee_id', 'idx_leave_balance_employee');
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            // Drop unique index cũ (employee_id, year) để hỗ trợ phân loại theo leave_type
            $table->dropUnique('uk_emp_leave_year');

            $table->enum('leave_type', ['ANNUAL', 'COMPENSATION'])
                ->default('ANNUAL')
                ->after('year')
                ->comment('Phân loại quỹ: Phép năm hoặc Nghỉ bù OT');
            $table->decimal('carried_over_days', 4, 2)
                ->default(0.00)
                ->after('leave_type')
                ->comment('Số phép tồn năm trước chuyển sang');
            $table->date('carried_over_expiry_date')
                ->nullable()
                ->after('carried_over_days')
                ->comment('Hạn sử dụng phép tồn (mặc định đến 31/03 năm kế tiếp)');
            $table->decimal('statutory_allocated_days', 4, 2)
                ->default(12.00)
                ->after('carried_over_expiry_date')
                ->comment('Định mức phép chuẩn theo luật (12 ngày thường, 14 ngày độc hại)');
            $table->decimal('welfare_bonus_days', 4, 2)
                ->default(0.00)
                ->after('seniority_bonus_days')
                ->comment('Phép cộng thêm từ chế độ phúc lợi doanh nghiệp');
            $table->decimal('total_entitled_days', 4, 2)
                ->default(12.00)
                ->after('welfare_bonus_days')
                ->comment('Tổng số ngày phép được hưởng trong năm');
            $table->decimal('accrued_days_to_date', 4, 2)
                ->default(0.00)
                ->after('total_entitled_days')
                ->comment('Số ngày phép đã tích lũy lũy kế tính đến tháng hiện tại');
            $table->decimal('used_days', 4, 2)
                ->default(0.00)
                ->after('accrued_days_to_date')
                ->comment('Số ngày phép đã sử dụng');
            $table->decimal('pending_days', 4, 2)
                ->default(0.00)
                ->after('used_days')
                ->comment('Số ngày phép đang trong đơn chờ duyệt');
            $table->decimal('remaining_days', 4, 2)
                ->default(12.00)
                ->after('pending_days')
                ->comment('Số ngày phép khả dụng còn lại');

            $table->unique(['employee_id', 'year', 'leave_type'], 'uk_emp_leave_year_type');
            $table->index(['tenant_id', 'year', 'leave_type'], 'idx_tenant_leave_year_type');
        });

        // 2. Bảng lịch sử biến động quỹ phép (Leave Transactions)
        Schema::create('leave_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->cascadeOnDelete();
            $table->enum('transaction_type', [
                'ACCRUAL',
                'USAGE',
                'EXPIRATION',
                'ADJUSTMENT',
                'COMPENSATION_CONVERT'
            ])->comment('Loại biến động: Tích lũy, Sử dụng, Hết hạn, Điều chỉnh, Quy đổi OT sang nghỉ bù');
            $table->decimal('days', 4, 2)->comment('Số ngày biến động (+ hoặc -)');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID tham chiếu đơn từ hoặc bảng chấm công');
            $table->string('description', 255)->nullable()->comment('Ghi chú chi tiết lý do biến động');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['leave_balance_id', 'transaction_type'], 'idx_trans_balance_type');
            $table->index('reference_id', 'idx_trans_reference');
        });

        // 3. Bảng theo dõi lũy kế giờ làm thêm năm (Overtime Accumulations)
        Schema::create('overtime_accumulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->comment('Năm theo dõi giờ OT');
            $table->decimal('total_ot_hours_year', 6, 2)->default(0.00)->comment('Tổng số giờ OT tích lũy trong năm');
            $table->json('monthly_ot_json')->nullable()->comment('Số giờ OT chi tiết từng tháng {"01": 10.5, "02": 15.0...}');
            $table->boolean('is_exceeded_200h')->default(false)->comment('Đã vượt trần OT 200 giờ/năm theo Điều 107');
            $table->boolean('is_exceeded_300h')->default(false)->comment('Đã vượt trần OT 300 giờ/năm theo Điều 107');
            $table->timestamps();

            $table->unique(['employee_id', 'year'], 'uk_emp_ot_year');
            $table->index(['tenant_id', 'year'], 'idx_tenant_ot_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_accumulations');
        Schema::dropIfExists('leave_transactions');

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropUnique('uk_emp_leave_year_type');
            $table->dropIndex('idx_tenant_leave_year_type');

            $table->dropColumn([
                'leave_type',
                'carried_over_days',
                'carried_over_expiry_date',
                'statutory_allocated_days',
                'welfare_bonus_days',
                'total_entitled_days',
                'accrued_days_to_date',
                'used_days',
                'pending_days',
                'remaining_days',
            ]);

            $table->unique(['employee_id', 'year'], 'uk_emp_leave_year');
            $table->dropIndex('idx_leave_balance_employee');
        });
    }
};
