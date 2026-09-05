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
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->comment('Phòng ban gắn liền (NULL nếu là vị trí dùng chung)');
            $table->string('code', 50)->comment('Mã chức vụ (VD: DEV_LEAD, HR_MGR, SALES_STAFF)');
            $table->string('name', 150)->comment('Tên chức vụ');
            $table->unsignedSmallInteger('level')->default(1)->comment('Cấp bậc chức danh (phục vụ duyệt hierarchical)');
            $table->decimal('allowance_amount', 15, 2)->default(0.00)->comment('Phụ cấp trách nhiệm/chức vụ hàng tháng (VND)');
            $table->decimal('bonus_one_time', 15, 2)->default(0.00)->comment('Thưởng bổ nhiệm 1 lần (VND)');
            $table->text('description')->nullable()->comment('Bản mô tả công việc (JD tóm tắt)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_position_code');
            $table->index(['tenant_id', 'department_id'], 'idx_tenant_dept_position');
        });

        // 6. Employees Table (Hồ sơ nhân sự toàn diện)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('user_code', 50)->comment('Mã nhân viên (VD: EMP001)');
            $table->string('attendance_code', 50)->nullable()->comment('Mã chấm công trên máy vân tay/face (enroll ID)');
            $table->string('full_name', 255);
            $table->enum('gender', ['MALE', 'FEMALE', 'OTHER']);
            $table->date('birth_date');
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('citizen_id', 20)->nullable()->comment('Số CCCD 12 chữ số');
            $table->text('encrypted_citizen_id')->nullable()->comment('Số CCCD mã hóa bằng AES-256-GCM');
            $table->string('hometown', 150)->nullable()->comment('Quê quán');
            $table->string('permanent_address', 255)->nullable()->comment('Địa chỉ thường trú');
            $table->string('temporary_address', 255)->nullable()->comment('Địa chỉ tạm trú / nơi ở hiện tại');
            $table->date('join_date')->comment('Ngày chính thức vào công ty');
            $table->date('probation_end_date')->nullable()->comment('Ngày kết thúc thử việc');

            // Thai sản thông minh (Lao động Nữ - Điều 137 Khoản 1 Điểm a BLLĐ)
            $table->boolean('is_pregnant')->default(false)->comment('Đang mang thai');
            $table->date('pregnancy_start_date')->nullable()->comment('Ngày bắt đầu thai kỳ / LMP');
            $table->unsignedTinyInteger('pregnancy_weeks_at_start')->nullable()->comment('Số tuần thai tại ngày khai báo');
            $table->date('expected_due_date')->nullable()->comment('Ngày dự kiến sinh');

            // Tổng hợp con nhỏ & người phụ thuộc (Cache/Tổng hợp từ employee_children)
            $table->unsignedTinyInteger('children_count')->default(0)->comment('Số lượng con nhỏ');
            $table->unsignedTinyInteger('tax_dependents_count')->default(0)->comment('Số người phụ thuộc giảm trừ gia cảnh thuế TNCN');

            // Trạng thái công tác & Nghỉ việc (Offboarding)
            $table->enum('status', ['PROBATION', 'OFFICIAL', 'ON_LEAVE', 'TERMINATED'])->default('PROBATION');
            $table->date('resigned_date')->nullable()->comment('Ngày chính thức thôi việc');
            $table->string('resignation_reason', 255)->nullable()->comment('Lý do thôi việc');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_code'], 'uk_tenant_user_code');
            $table->unique(['tenant_id', 'citizen_id'], 'uk_tenant_citizen_id');
            $table->index(['tenant_id', 'attendance_code'], 'idx_tenant_attendance_code');
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            $table->index(['tenant_id', 'join_date'], 'idx_tenant_join_date');
            $table->index(['tenant_id', 'resigned_date'], 'idx_tenant_resigned_date');
        });

        // Add foreign key manager_id on department_versions
        Schema::table('department_versions', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        // 7. Employee Children Table (Bảng Quản lý Con nhỏ & Phúc lợi Gia đình)
        Schema::create('employee_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('full_name', 255)->comment('Họ và tên con');
            $table->enum('gender', ['MALE', 'FEMALE', 'OTHER'])->default('MALE');
            $table->date('birth_date')->comment('Ngày sinh của con');
            $table->string('birth_certificate_number', 100)->nullable()->comment('Số giấy khai sinh');
            $table->boolean('is_tax_dependent')->default(true)->comment('Đăng ký người phụ thuộc giảm trừ thuế TNCN');
            $table->string('tax_dependent_code', 50)->nullable()->comment('Mã số thuế người phụ thuộc');
            $table->date('tax_dependent_start_date')->nullable()->comment('Ngày bắt đầu giảm trừ thuế');
            $table->date('tax_dependent_end_date')->nullable()->comment('Ngày kết thúc giảm trừ thuế');
            $table->boolean('has_monthly_allowance')->default(true)->comment('Hưởng trợ cấp con nhỏ hàng tháng');
            $table->decimal('monthly_allowance_amount', 15, 2)->default(300000.00)->comment('Mức trợ cấp hàng tháng (VND)');
            $table->text('notes')->nullable()->comment('Ghi chú sức khỏe/học tập');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id'], 'idx_tenant_emp_child');
            $table->index(['employee_id', 'birth_date'], 'idx_emp_child_birth');
            $table->index(['tenant_id', 'is_tax_dependent'], 'idx_tenant_child_tax');
            $table->index(['tenant_id', 'has_monthly_allowance'], 'idx_tenant_child_allowance');
            $table->index(['tenant_id', 'is_active'], 'idx_tenant_child_active');
        });

        // 8. Employee Certificates Table (Bảng Quản lý Bằng cấp, Chứng chỉ & Phụ cấp năng lực)
        Schema::create('employee_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name', 255)->comment('Tên bằng cấp / chứng chỉ');
            $table->enum('certificate_type', [
                'DEGREE',
                'PROFESSIONAL_CERTIFICATE',
                'LANGUAGE',
                'SAFETY',
                'OTHER'
            ])->default('PROFESSIONAL_CERTIFICATE');
            $table->string('field_of_study', 150)->nullable()->comment('Chuyên ngành đào tạo');
            $table->string('issued_by', 255)->comment('Cơ sở / Tổ chức cấp bằng');
            $table->date('issue_date')->comment('Ngày cấp');
            $table->date('expiry_date')->nullable()->comment('Ngày hết hạn (NULL nếu vĩnh viễn)');
            $table->decimal('monthly_allowance', 15, 2)->default(0.00)->comment('Phụ cấp chuyên môn hàng tháng (VND)');
            $table->decimal('one_time_reward', 15, 2)->default(0.00)->comment('Thưởng 1 lần khi đạt chứng chỉ (VND)');
            $table->string('file_url', 500)->nullable()->comment('Đường dẫn file scan / ảnh chứng chỉ');
            $table->boolean('is_verified')->default(false)->comment('Đã được HR/Quản lý thẩm định');
            $table->foreignId('verified_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id'], 'idx_tenant_emp_cert');
            $table->index(['employee_id', 'certificate_type'], 'idx_emp_cert_type');
            $table->index(['tenant_id', 'is_verified'], 'idx_tenant_cert_verified');
            $table->index(['expiry_date'], 'idx_cert_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certificates');
        Schema::dropIfExists('employee_children');
        Schema::table('department_versions', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
    }
};
