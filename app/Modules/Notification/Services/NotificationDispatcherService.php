<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Models\User;
use App\Modules\Notification\Models\AppNotification;
use App\Modules\Notification\Models\NotificationChannelConfig;
use App\Modules\Notification\Services\ChannelProviders\TelegramChannelProvider;
use App\Modules\Notification\Services\ChannelProviders\WebhookChannelProvider;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class NotificationDispatcherService
{
    public function __construct(
        protected TelegramChannelProvider $telegramProvider,
        protected WebhookChannelProvider $webhookProvider
    ) {}

    /**
     * Gửi thông báo đến một người dùng cụ thể
     */
    public function sendToUser(
        int $tenantId,
        int $userId,
        string $type,
        string $title,
        string $content,
        ?string $actionUrl = null,
        ?array $payload = null,
        string $severity = 'INFO',
        ?int $employeeId = null
    ): AppNotification {
        $channelsSent = ['in_app'];

        // 1. Tạo bản ghi thông báo In-App
        $notification = AppNotification::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'type' => $type,
            'severity' => strtoupper($severity),
            'title' => $title,
            'content' => $content,
            'action_url' => $actionUrl,
            'data_payload' => $payload,
            'is_read' => false,
            'channels_sent' => $channelsSent,
        ]);

        // 2. Kiểm tra và dispatch các kênh mở rộng (Telegram, Webhook)
        $this->dispatchExternalChannels($tenantId, $notification);

        return $notification;
    }

    /**
     * Gửi thông báo broadcast đến tất cả người dùng thuộc nhóm vai trò chỉ định
     */
    public function broadcastToRole(
        int $tenantId,
        string|array $roles,
        string $type,
        string $title,
        string $content,
        ?string $actionUrl = null,
        ?array $payload = null,
        string $severity = 'INFO'
    ): int {
        $roleList = is_array($roles) ? $roles : [$roles];
        
        $users = User::where('tenant_id', $tenantId)
            ->whereIn('role', $roleList)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $this->sendToUser(
                tenantId: $tenantId,
                userId: $user->id,
                type: $type,
                title: $title,
                content: $content,
                actionUrl: $actionUrl,
                payload: $payload,
                severity: $severity,
                employeeId: $user->employee_id ?? $user->employee?->id
            );
            $count++;
        }

        return $count;
    }

    /**
     * Gửi thông báo hệ thống đến toàn bộ người dùng trong Tenant
     */
    public function broadcastToAll(
        int $tenantId,
        string $title,
        string $content,
        ?string $actionUrl = null,
        string $severity = 'INFO'
    ): int {
        $users = User::where('tenant_id', $tenantId)->get();
        $count = 0;

        foreach ($users as $user) {
            $this->sendToUser(
                tenantId: $tenantId,
                userId: $user->id,
                type: 'SYSTEM_BROADCAST',
                title: $title,
                content: $content,
                actionUrl: $actionUrl,
                severity: $severity,
                employeeId: $user->employee_id ?? $user->employee?->id
            );
            $count++;
        }

        return $count;
    }

    /**
     * Đánh dấu một thông báo đã đọc
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = AppNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        return $notification->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
    }

    /**
     * Đánh dấu tất cả thông báo của người dùng đã đọc
     */
    public function markAllAsRead(int $userId): int
    {
        return AppNotification::forUser($userId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
    }

    /**
     * Xóa/hủy một thông báo
     */
    public function dismiss(int $notificationId, int $userId): bool
    {
        return (bool) AppNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Lấy danh sách thông báo gần nhất của người dùng
     */
    public function getRecentNotificationsForUser(User $user, int $limit = 10): Collection
    {
        return AppNotification::forUser($user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Đếm số lượng thông báo chưa đọc của người dùng
     */
    public function getUnreadCountForUser(User $user): int
    {
        return AppNotification::forUser($user->id)
            ->unread()
            ->count();
    }

    /**
     * Xử lý dispatch thông báo ra các kênh bên ngoài (Telegram, Webhook)
     */
    protected function dispatchExternalChannels(int $tenantId, AppNotification $notification): void
    {
        $activeConfigs = NotificationChannelConfig::where('tenant_id', $tenantId)
            ->active()
            ->get();

        if ($activeConfigs->isEmpty()) {
            return;
        }

        $channelsSent = $notification->channels_sent ?? ['in_app'];

        foreach ($activeConfigs as $config) {
            // Kiểm tra xem loại sự kiện này có đăng ký nhận thông báo qua kênh hay không
            $subscriptions = $config->event_subscriptions ?? [];
            if (!empty($subscriptions) && !in_array($notification->type, $subscriptions, true)) {
                continue;
            }

            $settings = $config->settings_json ?? [];

            if ($config->channel === 'TELEGRAM') {
                $sent = $this->telegramProvider->send(
                    settings: $settings,
                    title: $notification->title,
                    content: $notification->content,
                    severity: $notification->severity,
                    actionUrl: $notification->action_url
                );
                if ($sent) {
                    $channelsSent[] = 'telegram';
                }
            } elseif ($config->channel === 'WEBHOOK') {
                $sent = $this->webhookProvider->send(
                    settings: $settings,
                    payload: [
                        'id' => $notification->id,
                        'tenant_id' => $notification->tenant_id,
                        'type' => $notification->type,
                        'severity' => $notification->severity,
                        'title' => $notification->title,
                        'content' => $notification->content,
                        'action_url' => $notification->action_url,
                        'data_payload' => $notification->data_payload,
                        'timestamp' => Carbon::now()->toIso8601String(),
                    ]
                );
                if ($sent) {
                    $channelsSent[] = 'webhook';
                }
            }
        }

        $notification->update(['channels_sent' => array_values(array_unique($channelsSent))]);
    }
}
