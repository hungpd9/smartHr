<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 19. Leave Balances Table (Quỹ phép năm, thâm niên & bù OT)
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->comment('Năm áp dụng');
            $table->decimal('annual_entitlement', 4, 2)->default(12.00)->comment('Phép năm chuẩn (Đ.113 BLLĐ: 12 ngày)');
            $table->decimal('seniority_bonus_days', 4, 2)->default(0.00)->comment('+1 ngày mỗi 5 năm (Đ.114 BLLĐ)');
            $table->decimal('carried_forward_days', 4, 2)->default(0.00)->comment('Phép năm trước chuyển sang');
            $table->decimal('comp_off_hours', 5, 2)->default(0.00)->comment('Quỹ nghỉ bù tích lũy từ OT');
            $table->decimal('taken_paid_days', 4, 2)->default(0.00);
            $table->decimal('taken_unpaid_days', 4, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['employee_id', 'year'], 'uk_emp_leave_year');
        });

        // 20. Approval Requests Table (Đơn từ động JSON Schema & Điều 137 BLLĐ)
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('request_type', [
                'LEAVE',
                'OVERTIME',
                'NIGHT_SHIFT',
                'TIMESHEET_EDIT',
                'SHIFT_SWAP',
                'RESIGNATION',
                'REMOTE_WORK'
            ]);
            $table->json('form_payload')->comment('Dữ liệu biểu mẫu động theo JSON Schema');
            $table->boolean('is_voluntary_consent')->default(false)->comment('Cam kết tự nguyện làm đêm/OT cho mẹ nuôi con < 12 tháng (Đ.137 Khoản 1 Điểm b)');
            $table->string('voluntary_agreement_file', 255)->nullable()->comment('Tệp đính kèm đơn thỏa thuận (tùy chọn)');
            $table->string('voluntary_agreement_url', 500)->nullable()->comment('URL tài liệu ngoài (Google Drive, OneDrive) nhằm giảm tải lưu trữ');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('snapshot_approvers')->comment('Snapshot danh sách người duyệt tại thời điểm nộp đơn');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_tenant_req_status');
            $table->index(['employee_id', 'status'], 'idx_emp_req_status');
        });

        // 21. Approval Histories Table (Lịch sử phê duyệt từng cấp)
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->enum('action', ['APPROVE', 'REJECT', 'FORWARD', 'REQUEST_INFO']);
            $table->text('comment')->nullable();
            $table->boolean('verified_child_under_12m_consent')->default(false)->comment('Cấp duyệt xác nhận đã đối soát văn bản thỏa thuận Điều 137');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['request_id', 'step'], 'idx_req_step');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('leave_balances');
    }
};
