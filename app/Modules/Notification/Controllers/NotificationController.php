<?php

declare(strict_types=1);

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\AppNotification;
use App\Modules\Notification\Models\NotificationChannelConfig;
use App\Modules\Notification\Services\ChannelProviders\TelegramChannelProvider;
use App\Modules\Notification\Services\ChannelProviders\WebhookChannelProvider;
use App\Modules\Notification\Services\NotificationDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationDispatcherService $dispatcher,
        protected TelegramChannelProvider $telegramProvider,
        protected WebhookChannelProvider $webhookProvider
    ) {}

    /**
     * Màn hình danh sách thông báo và cấu hình kênh gửi
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;

        $filter = $request->string('filter', 'all')->value();
        $severity = $request->string('severity')->value();
        $type = $request->string('type')->value();
        $search = $request->string('search')->value();

        $query = AppNotification::forUser($user->id)
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'read', fn ($q) => $q->where('is_read', true))
            ->when($severity, fn ($q) => $q->where('severity', strtoupper($severity)))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');

        $notifications = $query->paginate(15)->withQueryString();

        // Thống kê nhanh cho người dùng
        $stats = [
            'total' => AppNotification::forUser($user->id)->count(),
            'unread' => AppNotification::forUser($user->id)->unread()->count(),
            'critical' => AppNotification::forUser($user->id)->where('severity', 'CRITICAL')->unread()->count(),
            'warning' => AppNotification::forUser($user->id)->where('severity', 'WARNING')->unread()->count(),
        ];

        // Cấu hình các kênh mở rộng (chỉ nạp cho HR_ADMIN / SUPER_ADMIN)
        $channelConfigs = [];
        $roleVal = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role;
        $canManageChannels = in_array($roleVal, ['SUPER_ADMIN', 'HR_ADMIN', 'ADMIN'], true);

        if ($canManageChannels) {
            $configs = NotificationChannelConfig::where('tenant_id', $tenantId)->get()->keyBy('channel');
            foreach (['TELEGRAM', 'WEBHOOK', 'EMAIL'] as $ch) {
                $channelConfigs[$ch] = $configs->get($ch) ?? [
                    'channel' => $ch,
                    'is_active' => false,
                    'settings_json' => [],
                    'event_subscriptions' => [
                        'COMPLIANCE_CRITICAL_ALERT',
                        'WORKFLOW_PENDING_APPROVAL',
                    ],
                ];
            }
        }

        // Danh mục các loại sự kiện hỗ trợ
        $eventTypes = [
            ['value' => 'WORKFLOW_PENDING_APPROVAL', 'label' => 'Đơn từ chờ duyệt'],
            ['value' => 'WORKFLOW_APPROVED', 'label' => 'Đơn từ đã được duyệt'],
            ['value' => 'WORKFLOW_REJECTED', 'label' => 'Đơn từ bị từ chối'],
            ['value' => 'COMPLIANCE_CRITICAL_ALERT', 'label' => 'Vi phạm BLLĐ nghiêm trọng (Đỏ)'],
            ['value' => 'COMPLIANCE_WARNING_ALERT', 'label' => 'Cảnh báo BLLĐ cận trần (Vàng)'],
            ['value' => 'TIMESHEET_SUBMITTED', 'label' => 'Bảng công đã nộp duyệt'],
            ['value' => 'TIMESHEET_APPROVED', 'label' => 'Bảng công đã được phê duyệt'],
            ['value' => 'PAYSLIP_ISSUED', 'label' => 'Phiếu lương điện tử phát hành'],
            ['value' => 'ROSTER_PUBLISHED', 'label' => 'Lịch phân ca mới được công bố'],
            ['value' => 'SYSTEM_BROADCAST', 'label' => 'Thông báo toàn hệ thống'],
        ];

        return Inertia::render('Notification/Index', [
            'notifications' => $notifications,
            'stats' => $stats,
            'filters' => [
                'filter' => $filter,
                'severity' => $severity,
                'type' => $type,
                'search' => $search,
            ],
            'channelConfigs' => $channelConfigs,
            'canManageChannels' => $canManageChannels,
            'eventTypes' => $eventTypes,
        ]);
    }

    /**
     * Đánh dấu một thông báo đã đọc
     */
    public function markRead(Request $request, int $id): RedirectResponse
    {
        $this->dispatcher->markAsRead($id, (int) $request->user()->id);

        return back()->with('success', 'Đã đánh dấu thông báo là đã đọc.');
    }

    /**
     * Đánh dấu tất cả thông báo của người dùng đã đọc
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $count = $this->dispatcher->markAllAsRead((int) $request->user()->id);

        return back()->with('success', "Đã đánh dấu {$count} thông báo là đã đọc.");
    }

    /**
     * Xóa một thông báo
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->dispatcher->dismiss($id, (int) $request->user()->id);

        return back()->with('success', 'Đã xóa thông báo.');
    }

    /**
     * Lưu thiết lập cấu hình kênh gửi (Telegram / Webhook)
     */
    public function saveChannelConfig(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'channel' => 'required|in:TELEGRAM,WEBHOOK,EMAIL,ZALO_ZNS',
            'is_active' => 'required|boolean',
            'settings_json' => 'nullable|array',
            'event_subscriptions' => 'nullable|array',
        ]);

        NotificationChannelConfig::updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'channel' => $validated['channel'],
            ],
            [
                'is_active' => $validated['is_active'],
                'settings_json' => $validated['settings_json'] ?? [],
                'event_subscriptions' => $validated['event_subscriptions'] ?? [],
            ]
        );

        return back()->with('success', "Cập nhật cấu hình kênh {$validated['channel']} thành công.");
    }

    /**
     * Gửi thử nghiệm kết nối kênh thông báo
     */
    public function testChannel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:TELEGRAM,WEBHOOK',
            'settings_json' => 'required|array',
        ]);

        $channel = $validated['channel'];
        $settings = $validated['settings_json'];
        $success = false;

        if ($channel === 'TELEGRAM') {
            $success = $this->telegramProvider->send(
                settings: $settings,
                title: 'Kiểm Tra Kết Nối Smart-HR',
                content: 'Tin nhắn kiểm tra từ nền tảng Smart-HR Enterprise. Kết nối Telegram Bot thành công!',
                severity: 'SUCCESS',
                actionUrl: '/notifications'
            );
        } elseif ($channel === 'WEBHOOK') {
            $success = $this->webhookProvider->send(
                settings: $settings,
                payload: [
                    'event' => 'PING_TEST',
                    'message' => 'Kiểm tra kết nối Webhook từ Smart-HR',
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        if ($success) {
            return response()->json(['success' => true, 'message' => "Gửi thử nghiệm qua kênh {$channel} thành công!"]);
        }

        return response()->json(['success' => false, 'message' => "Không thể gửi tin nhắn qua kênh {$channel}. Vui lòng kiểm tra lại tham số cấu hình."], 422);
    }
}
