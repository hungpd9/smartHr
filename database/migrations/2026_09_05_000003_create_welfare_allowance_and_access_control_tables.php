<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Payroll Policy Configs (Cấu hình Lương, Thuế, Bảo hiểm gắn với Policy Version theo Zero-Hardcode & Effective Dating)
        Schema::create('payroll_policy_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('policy_versions')->cascadeOnDelete();
            
            // Ngày công & giờ làm việc chuẩn
            $table->enum('standard_working_days_mode', ['FIXED', 'CALENDAR_BASED'])->default('FIXED')
                ->comment('Chế độ ngày công chuẩn: Cố định hoặc tính theo lịch thực tế');
            $table->decimal('fixed_standard_working_days', 4, 2)->default(26.00)
                ->comment('Số ngày công chuẩn cố định trong tháng (VD: 26.0, 24.0)');
            $table->decimal('standard_working_hours_per_day', 4, 2)->default(8.00)
                ->comment('Số giờ làm việc chuẩn mỗi ngày');
            
            // Ngày chi trả lương
            $table->unsignedTinyInteger('payment_day')->default(10)
                ->comment('Ngày thanh toán lương trong tháng');
            $table->boolean('pay_early_on_holiday_weekend')->default(true)
                ->comment('Chi trả sớm nếu ngày thanh toán rơi vào Thứ 7, CN hoặc Ngày lễ');

            // Mức giảm trừ gia cảnh thuế TNCN
            $table->decimal('personal_deduction', 15, 2)->default(11000000.00)
                ->comment('Giảm trừ bản thân (11.000.000 hoặc 15.400.000 đ)');
            $table->decimal('dependent_deduction', 15, 2)->default(4400000.00)
                ->comment('Giảm trừ mỗi người phụ thuộc (4.400.000 hoặc 6.160.000 đ)');
            $table->decimal('default_child_allowance', 15, 2)->default(300000.00)
                ->comment('Mức trợ cấp con nhỏ mặc định (300.000 đ/bé/tháng)');

            // Lương cơ sở và Mức trần đóng BHXH/BHTN
            $table->decimal('base_salary', 15, 2)->default(2340000.00)
                ->comment('Lương cơ sở áp dụng từ 01/07/2024');
            $table->decimal('max_salary_bhxh', 15, 2)->default(46800000.00)
                ->comment('Mức trần đóng BHXH/BHYT (20 lần lương cơ sở)');
            $table->decimal('max_salary_bhtn', 15, 2)->default(99200000.00)
                ->comment('Mức trần đóng BHTN (20 lần lương tối thiểu vùng)');

            // Tỷ lệ trích nộp bảo hiểm bắt buộc Doanh nghiệp
            $table->decimal('company_bhxh_rate', 5, 2)->default(17.50)->comment('BHXH Doanh nghiệp 17.5%');
            $table->decimal('company_bhyt_rate', 5, 2)->default(3.00)->comment('BHYT Doanh nghiệp 3.0%');
            $table->decimal('company_bhtn_rate', 5, 2)->default(1.00)->comment('BHTN Doanh nghiệp 1.0%');

            // Tỷ lệ trích nộp bảo hiểm bắt buộc Người lao động
            $table->decimal('employee_bhxh_rate', 5, 2)->default(8.00)->comment('BHXH Người lao động 8.0%');
            $table->decimal('employee_bhyt_rate', 5, 2)->default(1.50)->comment('BHYT Người lao động 1.5%');
            $table->decimal('employee_bhtn_rate', 5, 2)->default(1.00)->comment('BHTN Người lao động 1.0%');

            // Thử việc & Vãng lai
            $table->decimal('probation_salary_percentage', 5, 2)->default(85.00)->comment('Tỷ lệ lương thử việc tối thiểu (85% theo BLLĐ)');
            $table->decimal('probation_pit_rate', 5, 2)->default(10.00)->comment('Thuế suất TNCN thử việc / vãng lai (10%)');
            $table->decimal('probation_pit_threshold', 15, 2)->default(2000000.00)->comment('Ngưỡng thu nhập bắt đầu khấu trừ 10% (2.000.000 đ)');

            // Các hệ số OT
            $table->decimal('ot_weekday_day_rate', 4, 2)->default(1.50)->comment('OT ngày thường ban ngày: 150%');
            $table->decimal('ot_weekday_night_rate', 4, 2)->default(2.00)->comment('OT ngày thường ban đêm: 200%');
            $table->decimal('ot_weekend_day_rate', 4, 2)->default(2.00)->comment('OT cuối tuần ban ngày: 200%');
            $table->decimal('ot_weekend_night_rate', 4, 2)->default(2.70)->comment('OT cuối tuần ban đêm: 270%');
            $table->decimal('ot_holiday_day_rate', 4, 2)->default(3.00)->comment('OT ngày lễ ban ngày: 300%');
            $table->decimal('ot_holiday_night_rate', 4, 2)->default(3.90)->comment('OT ngày lễ ban đêm: 390%');

            // Trần bóc tách thu nhập OT miễn thuế (Điều 107 & NĐ 145/2020)
            $table->unsignedSmallInteger('ot_tax_exempt_daily_limit_percent')->default(50)->comment('Trần OT ngày: 50% số giờ làm việc chuẩn');
            $table->unsignedSmallInteger('ot_tax_exempt_monthly_limit_hours')->default(40)->comment('Trần OT tháng: 40 giờ');
            $table->unsignedSmallInteger('ot_tax_exempt_yearly_limit_hours')->default(200)->comment('Trần OT năm thông thường: 200 giờ (300h ngành nghề đặc biệt)');

            $table->timestamps();
        });

        // 2. Chế độ Phúc lợi Doanh nghiệp Đa năng (Welfare Schemes - Cho phép tạo chế độ linh hoạt, quản lý Ẩn/Hiện hidden/visible)
        Schema::create('welfare_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('policy_version_id')->nullable()->constrained('policy_versions')->nullOnDelete();
            
            $table->string('code', 50)->comment('Mã chế độ (VD: SCHEME_CON_NHO_11T, SCHEME_THAI_SAN)');
            $table->string('name', 200)->comment('Tên chế độ phúc lợi');
            $table->enum('category', [
                'CHILD_CARE',       // Chăm sóc con nhỏ & gia đình
                'MATERNITY_FEMALE', // Thai sản & sức khỏe phụ nữ
                'HEALTH_WELLNESS',  // Sức khỏe, y tế doanh nghiệp
                'WORKING_COND',     // Điều kiện làm việc & môi trường
                'FINANCIAL_SUPPORT',// Hỗ trợ tài chính, hiếu hỉ, nhà ở
                'SPECIAL_ALLOWANCE' // Phụ cấp chuyên biệt
            ])->default('CHILD_CARE');

            $table->enum('visibility', ['VISIBLE', 'HIDDEN'])->default('VISIBLE')
                ->comment('Trạng thái Ẩn / Hiện chế độ phúc lợi (hidden / visible)');
            $table->boolean('is_statutory')->default(false)
                ->comment('TRUE nếu là chế độ bắt buộc của BLLĐ (VD: Điều 137)');

            // Tác động Chấm công & Phân ca
            $table->integer('daily_exemption_minutes')->default(0)->comment('Số phút miễn trừ đi muộn/về sớm mỗi ngày làm việc');
            $table->enum('pay_type', ['PAID', 'UNPAID', 'PARTIAL'])->default('PAID')->comment('Hưởng lương khi áp dụng miễn trừ');
            $table->boolean('prohibit_night_shift')->default(false)->comment('Cấm làm ca đêm');
            $table->boolean('prohibit_overtime')->default(false)->comment('Cấm làm thêm giờ (OT)');
            $table->decimal('additional_leave_days_per_year', 4, 1)->default(0.0)->comment('Số ngày nghỉ phép có lương cộng thêm');

            // Tác động Tiền lương & Trợ cấp
            $table->decimal('monthly_allowance_amount', 15, 2)->default(0.00)->comment('Mức trợ cấp tài chính hàng tháng');
            $table->decimal('one_time_bonus_amount', 15, 2)->default(0.00)->comment('Mức thưởng 1 lần khi đủ điều kiện');
            $table->boolean('is_tax_exempt')->default(false)->comment('Được miễn thuế TNCN hay không');

            // Tiêu chí áp dụng tự động
            $table->enum('applicable_gender', ['ALL', 'FEMALE', 'MALE'])->default('ALL');
            $table->integer('child_min_age_months')->nullable()->comment('Độ tuổi con tối thiểu (tháng)');
            $table->integer('child_max_age_months')->nullable()->comment('Độ tuổi con tối đa (tháng) (VD: 132 tháng = 11 tuổi)');
            $table->integer('min_seniority_months')->default(0)->comment('Thâm niên làm việc tối thiểu để được hưởng');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_welfare_code');
            $table->index(['tenant_id', 'visibility', 'category'], 'idx_tenant_welfare_vis');
        });

        // 3. Danh mục Phụ cấp Doanh nghiệp (Allowances - Kế thừa & nâng tầm từ timekeeper2)
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50)->comment('Mã phụ cấp (VD: PC_ANTRUA, PC_XANGXE, PC_DIENTHOAI, PC_TRACHNHIEM)');
            $table->string('name', 200)->comment('Tên khoản phụ cấp');
            
            $table->enum('apply_type', ['COMPANY_FIXED', 'INDIVIDUAL_FIXED', 'IRREGULAR'])->default('COMPANY_FIXED')
                ->comment('Hình thức áp dụng: Cố định toàn công ty, Cố định theo cá nhân, hoặc Biến động theo tháng');
            
            $table->boolean('is_insurance_taxable')->default(false)->comment('Có tính vào quỹ đóng BHXH bắt buộc');
            $table->boolean('is_pit_taxable')->default(true)->comment('Có tính vào thu nhập chịu thuế TNCN');
            $table->boolean('is_included_in_ot_wage')->default(false)->comment('Có tính vào đơn giá giờ lương để nhân hệ số OT');
            
            $table->decimal('tax_exemption_limit', 15, 2)->default(0.00)->comment('Mức trần miễn thuế TNCN (VD: Ăn ca 730.000 đ/tháng)');
            $table->decimal('default_amount', 15, 2)->default(0.00)->comment('Số tiền mặc định (VND)');
            
            $table->enum('visibility', ['VISIBLE', 'HIDDEN'])->default('VISIBLE')->comment('Ẩn / Hiện danh mục phụ cấp');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uk_tenant_allowance_code');
            $table->index(['tenant_id', 'visibility', 'apply_type'], 'idx_tenant_allowance_vis');
        });

        // 4. Ma Trận Phân Quyền & Ủy Quyền Hệ Thống (Granular Matrix Access Control)
        Schema::create('permission_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            $table->enum('subject_type', ['ROLE', 'DEPARTMENT', 'POSITION', 'USER'])
                ->comment('Đối tượng được phân quyền: Theo vai trò, theo phòng ban, theo chức vụ hoặc cá nhân cụ thể');
            $table->unsignedBigInteger('subject_id')->nullable()
                ->comment('ID của Department, Position hoặc User (NULL nếu phân quyền theo vai trò Role toàn cục)');
            $table->string('subject_role', 50)->nullable()
                ->comment('Tên vai trò (VD: SUPER_ADMIN, HR_ADMIN, ACCOUNTANT, EMPLOYEE) nếu subject_type = ROLE');

            $table->enum('module_code', [
                'CORE_HR',          // Hồ sơ nhân sự & Sơ đồ tổ chức
                'PAYROLL_SALARY',   // Mức lương, cấu hình lương, bảng lương
                'ATTENDANCE',       // Điểm danh, chốt công, phân ca
                'POLICY_WELFARE',   // Quy chế, chế độ phúc lợi, phụ cấp
                'OFFICE_RESOURCES'  // Tài nguyên văn phòng, thiết bị, tạm ứng, VPP
            ])->comment('Mã phân hệ chức năng');

            $table->boolean('can_view')->default(false)->comment('Quyền Xem thông tin');
            $table->boolean('can_manage')->default(false)->comment('Quyền Thêm / Sửa / Xóa cấu hình hoặc dữ liệu');
            $table->boolean('can_approve')->default(false)->comment('Quyền Phê duyệt đơn từ / bảng tính');
            
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'subject_type', 'subject_id', 'subject_role', 'module_code'], 'uk_tenant_perm_assign');
            $table->index(['tenant_id', 'module_code'], 'idx_tenant_perm_module');
        });

        // 5. Cập nhật bảng child_welfare_policies cũ: thêm visibility để đồng bộ
        if (Schema::hasTable('child_welfare_policies') && !Schema::hasColumn('child_welfare_policies', 'visibility')) {
            Schema::table('child_welfare_policies', function (Blueprint $table) {
                $table->enum('visibility', ['VISIBLE', 'HIDDEN'])->default('VISIBLE')->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('child_welfare_policies') && Schema::hasColumn('child_welfare_policies', 'visibility')) {
            Schema::table('child_welfare_policies', function (Blueprint $table) {
                $table->dropColumn('visibility');
            });
        }

        Schema::dropIfExists('permission_assignments');
        Schema::dropIfExists('allowances');
        Schema::dropIfExists('welfare_schemes');
        Schema::dropIfExists('payroll_policy_configs');
    }
};
