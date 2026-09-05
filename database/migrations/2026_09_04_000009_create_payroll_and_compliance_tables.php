<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 22. Payroll Records Table (Bảng lương Gross-to-Net & Móc neo Mật mã AES-256-GCM)
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('month_year', 7)->comment('Kỳ lương (VD: 2026-09)');
            $table->decimal('gross_salary', 15, 2)->default(0.00);
            $table->decimal('position_allowance_amount', 15, 2)->default(0.00)->comment('Phụ cấp chức vụ / trách nhiệm');
            $table->decimal('child_allowance_amount', 15, 2)->default(0.00)->comment('Trợ cấp con nhỏ hàng tháng');
            $table->decimal('certificate_allowance_amount', 15, 2)->default(0.00)->comment('Phụ cấp bằng cấp / chứng chỉ chuyên môn');
            $table->decimal('tax_exempt_ot_amount', 15, 2)->default(0.00)->comment('Thu nhập OT miễn thuế (3 tầng trần NĐ 145/2020)');
            $table->decimal('taxable_ot_amount', 15, 2)->default(0.00)->comment('Thu nhập OT chịu thuế');
            $table->decimal('social_insurance_employee', 15, 2)->default(0.00)->comment('BHXH NLĐ 8%');
            $table->decimal('health_insurance_employee', 15, 2)->default(0.00)->comment('BHYT NLĐ 1.5%');
            $table->decimal('unemployment_insurance_employee', 15, 2)->default(0.00)->comment('BHTN NLĐ 1%');
            $table->decimal('pit_tax_amount', 15, 2)->default(0.00)->comment('Thuế TNCN lũy tiến 5 bậc');
            $table->decimal('net_salary', 15, 2)->default(0.00);
            $table->text('encrypted_salary_details')->nullable()->comment('Toàn bộ chi tiết lương mã hóa AES-256-GCM dẫn xuất từ License');
            $table->enum('status', ['DRAFT', 'APPROVED', 'PAID'])->default('DRAFT');
            $table->timestamps();

            $table->unique(['employee_id', 'month_year'], 'uk_emp_payroll_month');
            $table->index(['tenant_id', 'month_year'], 'idx_tenant_payroll_month');
        });

        // 23. Compliance Alerts Table (Cảnh báo tuân thủ thời gian thực)
        Schema::create('compliance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('alert_type', [
                'OT_WARNING_YELLOW_35H',
                'OT_BREACH_RED_40H',
                'OT_ANNUAL_CAP_BREACH',
                'REST_BETWEEN_SHIFTS_BREACH',
                'WEEKLY_REST_BREACH',
                'PREGNANT_NIGHT_BREACH',
                'CONTRACT_EXPIRING_30D',
                'BURNOUT_RISK_HIGH'
            ]);
            $table->enum('severity', ['INFO', 'WARNING', 'CRITICAL'])->default('WARNING');
            $table->string('title', 255);
            $table->text('description');
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_resolved', 'severity'], 'idx_tenant_compliance');
        });

        // 24. Audit Trails Table (Nhật ký kiểm toán hệ thống bất biến)
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable()->comment('Người thực hiện');
            $table->string('actor_type', 100)->default('EMPLOYEE');
            $table->string('action_type', 100)->comment('UPDATE_TIMESHEET, APPROVE_REQUEST, OVERRIDE_ROSTER, LOCK_MONTH');
            $table->string('auditable_type', 150);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'action_type'], 'idx_tenant_action');
            $table->index(['auditable_type', 'auditable_id'], 'idx_auditable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
        Schema::dropIfExists('compliance_alerts');
        Schema::dropIfExists('payroll_records');
    }
};
