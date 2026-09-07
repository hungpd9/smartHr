<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services\ChannelProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannelProvider
{
    /**
     * Gửi payload JSON thông báo đến Webhook Endpoint
     */
    public function send(array $settings, array $payload): bool
    {
        $webhookUrl = $settings['webhook_url'] ?? null;
        $secretToken = $settings['secret_token'] ?? null;

        if (!$webhookUrl || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Smart-HR-Webhook-Dispatcher/1.0',
            ];

            if ($secretToken) {
                $headers['X-SmartHR-Signature'] = hash_hmac('sha256', json_encode($payload), $secretToken);
            }

            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->post($webhookUrl, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Lỗi gửi thông báo Webhook: " . $e->getMessage());
            return false;
        }
    }
}
