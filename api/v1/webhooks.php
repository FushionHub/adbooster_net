<?php declare(strict_types=1);

/**
 * Outbound Webhook Sender
 * Adsbooster.net
 *
 * Fires webhooks on events: campaign status change, post published, payment completed.
 * Signs payloads with HMAC-SHA256 header for verification.
 */

require_once __DIR__ . '/../../config/db.php';

class WebhookSender
{
    public static function fire(int $userId, string $eventType, array $payload): void
    {
        $webhooks = db()->fetchAll(
            "SELECT id, target_url, secret
             FROM webhooks
             WHERE user_id = ? AND event_type = ? AND is_active = 1",
            [$userId, $eventType]
        );

        if (empty($webhooks)) {
            return;
        }

        $body = json_encode([
            'event'     => $eventType,
            'timestamp' => date('c'),
            'data'      => $payload,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($webhooks as $webhook) {
            self::deliver($webhook, $eventType, $body);
        }
    }

    private static function deliver(array $webhook, string $eventType, string $body): void
    {
        $signature = hash_hmac('sha256', $body, $webhook['secret']);

        $ch = curl_init($webhook['target_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Adsbooster-Event: ' . $eventType,
                'X-Adsbooster-Signature: sha256=' . $signature,
                'X-Adsbooster-Delivery: ' . bin2hex(random_bytes(16)),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            error_log("Webhook delivery failed to {$webhook['target_url']}: HTTP {$httpCode} - {$error}");

            db()->update('webhooks', [
                'is_active' => 0,
            ], 'id = ?', [$webhook['id']]);
        }
    }

    public static function onCampaignStatusChanged(array $campaign, string $oldStatus, string $newStatus): void
    {
        self::fire((int) $campaign['user_id'], 'campaign.status_changed', [
            'campaign_id'   => $campaign['id'],
            'campaign_name' => $campaign['campaign_name'],
            'old_status'    => $oldStatus,
            'new_status'    => $newStatus,
        ]);
    }

    public static function onPostPublished(array $post): void
    {
        self::fire((int) $post['user_id'], 'post.published', [
            'post_id'       => $post['id'],
            'platform'      => $post['platform'],
            'scheduled_time' => $post['scheduled_time'],
        ]);
    }

    public static function onPaymentCompleted(array $transaction): void
    {
        self::fire((int) $transaction['user_id'], 'payment.completed', [
            'transaction_id' => $transaction['id'],
            'amount'         => $transaction['amount'],
            'currency'       => $transaction['currency'],
            'type'           => $transaction['type'],
            'gateway'        => $transaction['gateway'],
        ]);
    }

    public static function testPing(int $webhookId, int $userId): array
    {
        $hook = db()->fetchOne("SELECT * FROM webhooks WHERE id = ? AND user_id = ?", [$webhookId, $userId]);
        if (!$hook) {
            return ['success' => false, 'error' => 'Webhook endpoint not found'];
        }

        $deliveryId = bin2hex(random_bytes(16));
        $body = json_encode([
            'event'       => 'webhook.test_ping',
            'timestamp'   => date('c'),
            'delivery_id' => $deliveryId,
            'data'        => [
                'message'    => 'This is a test notification from Adsbooster.net Developer Gateway.',
                'user_id'    => $userId,
                'target_url' => $hook['target_url'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $body, $hook['secret']);
        $start = microtime(true);

        $ch = curl_init($hook['target_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Adsbooster-Event: webhook.test_ping',
                'X-Adsbooster-Signature: sha256=' . $signature,
                'X-Adsbooster-Delivery: ' . $deliveryId,
                'User-Agent: Adsbooster-Webhook-Tester/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $latencyMs = round((microtime(true) - $start) * 1000, 1);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        return [
            'success'    => $isSuccess,
            'http_code'  => $httpCode,
            'latency_ms' => $latencyMs,
            'response'   => substr((string)$response, 0, 500),
            'error'      => $curlError ?: ($isSuccess ? null : "Receiver responded with HTTP {$httpCode}"),
            'signature'  => 'sha256=' . $signature,
        ];
    }
}

