<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services\ChannelProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannelProvider
{
    /**
     * Gửi tin nhắn thông báo đến Telegram Bot/Group
     */
    public function send(array $settings, string $title, string $content, string $severity, ?string $actionUrl = null): bool
    {
        $botToken = $settings['bot_token'] ?? null;
        $chatId = $settings['chat_id'] ?? null;

        if (!$botToken || !$chatId) {
            return false;
        }

        $icon = match ($severity) {
            'CRITICAL' => '🚨 [NGHIÊM TRỌNG]',
            'WARNING' => '⚠️ [CẢNH BÁO]',
            'SUCCESS' => '✅ [THÀNH CÔNG]',
            default => 'ℹ️ [THÔNG BÁO]',
        };

        $message = "<b>{$icon} {$title}</b>\n\n";
        $message .= htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
        
        if ($actionUrl) {
            $appUrl = config('app.url', 'http://localhost');
            $fullUrl = str_starts_with($actionUrl, 'http') ? $actionUrl : rtrim($appUrl, '/') . '/' . ltrim($actionUrl, '/');
            $message .= "<a href=\"{$fullUrl}\">👉 Xem chi tiết trên Smart-HR</a>";
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Lỗi gửi thông báo Telegram: " . $e->getMessage());
            return false;
        }
    }
}
