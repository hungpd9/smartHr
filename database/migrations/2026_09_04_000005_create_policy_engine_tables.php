<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 9. Policy Versions Table (Phiên bản chính sách Zero-Hardcode)
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50)->comment('Mã phiên bản (VD: POLICY_2026_V1)');
            $table->string('name', 255)->comment('Tên phiên bản quy chế');
            $table->date('effective_from')->comment('Ngày bắt đầu áp dụng');
            $table->date('effective_to')->nullable()->comment('Ngày kết thúc (NULL nếu đang hiệu lực)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'effective_from', 'effective_to'], 'idx_tenant_effective');
        });

        // 10. Attendance Policy Configs Table (Chi tiết nấc phạt, grace period, giờ nghỉ giữa ca)
        Schema::create('attendance_policy_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('policy_versions')->cascadeOnDelete();
            $table->integer('grace_period_late_minutes')->default(0)->comment('Số phút đi muộn cho phép không phạt');
            $table->integer('grace_period_early_minutes')->default(0)->comment('Số phút về sớm cho phép không phạt');
            $table->decimal('leave_unit_hours', 4, 2)->default(4.00)->comment('Đơn vị tính nghỉ phép (4h = nửa ngày, 8h = 1 ngày)');
            $table->decimal('early_leave_unit_hours', 4, 2)->default(0.25)->comment('Đơn vị làm tròn muộn/sớm (0.25h = 15p)');
            $table->integer('min_rest_hours_between_shifts')->default(12)->comment('Nghỉ tối thiểu giữa 2 ca (Đ.110 BLLĐ: 12h)');
            $table->integer('min_weekly_rest_hours')->default(24)->comment('Nghỉ hàng tuần liên tục (Đ.111 BLLĐ: 24h)');
            $table->integer('max_weekly_standard_hours')->default(48)->comment('Tối đa giờ làm chuẩn tuần (Đ.105 BLLĐ)');
            $table->decimal('ot_to_rostered_rate', 4, 2)->default(1.00);
            $table->decimal('compensation_rate', 4, 2)->default(8.00)->comment('Số giờ OT tương đương 1 ngày nghỉ bù');
            $table->json('penalty_tiers_json')->comment('Bảng nấc phạt theo phút: [{min:1, max:15, fine:50000}, ...]');
            $table->json('frequency_penalty_json')->comment('Hệ số phạt theo số lần trong tháng: [{count:3, mult:1.0}, ...]');
            $table->timestamps();
        });

        // 11. Child Welfare Policies Table (Ma trận phúc lợi con nhỏ & gia đình linh hoạt)
        Schema::create('child_welfare_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('policy_versions')->cascadeOnDelete();
            $table->string('name', 150)->comment('Tên chế độ (VD: Con dưới 1 tuổi, Con 1-11 tuổi)');
            $table->integer('min_age_months')->comment('Độ tuổi tối thiểu (tháng)');
            $table->integer('max_age_months')->comment('Độ tuổi tối đa (tháng)');
            $table->integer('daily_exemption_minutes')->comment('Số phút miễn trừ mỗi ngày làm việc (VD: 60)');
            $table->enum('pay_type', ['PAID', 'UNPAID', 'PARTIAL'])->comment('Hưởng lương hay không');
            $table->boolean('prohibit_night_shift')->default(false)->comment('Cấm làm ca đêm (TRUE với con < 12 tháng)');
            $table->boolean('prohibit_overtime')->default(false)->comment('Cấm làm thêm giờ (TRUE với con < 12 tháng)');
            $table->boolean('is_statutory')->default(false)->comment('TRUE nếu là quy định bắt buộc của BLLĐ 2019');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 12. PIT Tax Brackets Table (Biểu thuế TNCN 5 bậc hiệu lực từ 2026)
        Schema::create('pit_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('policy_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('tier')->comment('Bậc thuế (1, 2, 3, 4, 5)');
            $table->decimal('min_income', 15, 2)->comment('Thu nhập tính thuế từ (VND)');
            $table->decimal('max_income', 15, 2)->nullable()->comment('Thu nhập tính thuế đến (NULL nếu không giới hạn)');
            $table->decimal('tax_rate', 5, 2)->comment('Thuế suất (%)');
            $table->decimal('quick_subtraction', 15, 2)->default(0.00)->comment('Số trừ nhanh (VND)');
            $table->timestamps();

            $table->index(['policy_version_id', 'tier'], 'idx_policy_tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pit_tax_brackets');
        Schema::dropIfExists('child_welfare_policies');
        Schema::dropIfExists('attendance_policy_configs');
        Schema::dropIfExists('policy_versions');
    }
};
