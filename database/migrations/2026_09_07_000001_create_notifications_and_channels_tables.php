<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Bảng app_notifications (Thông báo In-App hướng đối tượng)
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('type', 80)->comment('Mã loại sự kiện thông báo: WORKFLOW_PENDING_APPROVAL, WORKFLOW_APPROVED, COMPLIANCE_CRITICAL_ALERT...');
            $table->enum('severity', ['INFO', 'WARNING', 'SUCCESS', 'CRITICAL'])->default('INFO');
            $table->string('title', 255);
            $table->text('content');
            $table->string('action_url', 255)->nullable()->comment('Đường dẫn trực tiếp khi người dùng click vào thông báo');
            $table->json('data_payload')->nullable()->comment('Metadata bổ sung: request_id, alert_id, period...');
            $table->boolean('is_read')->default(false)->comment('Trạng thái đã đọc');
            $table->dateTime('read_at')->nullable()->comment('Thời điểm đánh dấu đã đọc');
            $table->json('channels_sent')->nullable()->comment('Danh sách kênh đã dispatch: in_app, telegram, webhook...');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'is_read'], 'idx_tenant_user_read');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
        });

        // 2. Bảng notification_channel_configs (Cấu hình kênh gửi thông báo mở rộng)
        Schema::create('notification_channel_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('channel', ['TELEGRAM', 'WEBHOOK', 'EMAIL', 'ZALO_ZNS'])->comment('Loại kênh thông báo');
            $table->boolean('is_active')->default(false)->comment('Bật/tắt kênh');
            $table->json('settings_json')->nullable()->comment('Tham số kết nối: bot_token, chat_id, webhook_url, secret...');
            $table->json('event_subscriptions')->nullable()->comment('Danh sách mã sự kiện đăng ký nhận qua kênh này');
            $table->timestamps();

            $table->unique(['tenant_id', 'channel'], 'uk_tenant_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_channel_configs');
        Schema::dropIfExists('app_notifications');
    }
};
