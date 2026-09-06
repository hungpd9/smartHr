<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Thêm cấu hình chu trình đóng công & Auto-Submit vào attendance_policy_configs
        Schema::table('attendance_policy_configs', function (Blueprint $table) {
            $table->boolean('enable_auto_submit_timesheet')->default(true)->after('frequency_penalty_json')->comment('Bật/tắt tự động gửi bảng công tháng cho nhân viên');
            $table->unsignedTinyInteger('emp_review_deadline_day')->default(3)->after('enable_auto_submit_timesheet')->comment('Hạn nhân viên rà soát & nộp đơn bổ sung (Ngày 3)');
            $table->unsignedTinyInteger('auto_submit_day')->default(4)->after('emp_review_deadline_day')->comment('Ngày hệ thống tự động gửi bảng công lên quản lý (Ngày 4)');
            $table->unsignedTinyInteger('manager_approval_deadline_day')->default(5)->after('auto_submit_day')->comment('Hạn quản lý trực tiếp kiểm tra & duyệt (Ngày 5)');
            $table->unsignedTinyInteger('higher_manager_deadline_day')->default(6)->after('manager_approval_deadline_day')->comment('Hạn quản lý cấp trên duyệt (Ngày 6)');
            $table->unsignedTinyInteger('hr_lock_deadline_day')->default(7)->after('higher_manager_deadline_day')->comment('Hạn Trưởng HR/Giám đốc chốt công & khóa tháng (Ngày 7)');
            $table->unsignedTinyInteger('payroll_payment_day')->default(10)->after('hr_lock_deadline_day')->comment('Ngày chi trả lương định kỳ (Ngày 10)');
        });

        // 2. Thêm trạng thái chu trình & phê duyệt nhiều cấp vào monthly_attendances
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->string('submission_status', 30)->default('EMPLOYEE_REVIEW')->after('total_penalty_amount')->comment('Trạng thái vòng đời: EMPLOYEE_REVIEW, SUBMITTED, MANAGER_APPROVED, HIGHER_MANAGER_APPROVED, REJECTED, HR_LOCKED');
            $table->string('submission_type', 20)->nullable()->after('submission_status')->comment('MANUAL hoặc AUTO_SUBMITTED');
            $table->dateTime('submitted_at')->nullable()->after('submission_type');
            $table->text('rejection_reason')->nullable()->after('submitted_at');
            $table->foreignId('rejected_by')->nullable()->after('rejection_reason')->constrained('employees')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'submission_status',
                'submission_type',
                'submitted_at',
                'rejection_reason',
                'rejected_by',
                'rejected_at',
            ]);
        });

        Schema::table('attendance_policy_configs', function (Blueprint $table) {
            $table->dropColumn([
                'enable_auto_submit_timesheet',
                'emp_review_deadline_day',
                'auto_submit_day',
                'manager_approval_deadline_day',
                'higher_manager_deadline_day',
                'hr_lock_deadline_day',
                'payroll_payment_day',
            ]);
        });
    }
};
