<?php
declare(strict_types=1);

/**
 * Dispatches only endpoints explicitly enabled by an administrator.  Delivery
 * is intentionally synchronous and short-lived: this starter has no worker,
 * so failures are recorded rather than retried invisibly.
 */
final class WebhookDispatcher
{
    public function __construct(private WebhookRepository $webhooks)
    {
    }

    public function dispatch(string $eventName, array $data): void
    {
        if (!feature('webhooks') || !function_exists('curl_init')) {
            return;
        }
        $payload = json_encode([
            'event' => $eventName,
            'occurred_at' => gmdate('c'),
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        foreach ($this->webhooks->activeForEvent($eventName) as $endpoint) {
            $targetUrl = (string)$endpoint['target_url'];
            try {
                $target = WebhookTargetValidator::resolve($targetUrl);
            } catch (InvalidArgumentException $e) {
                $this->webhooks->recordDelivery((int)$endpoint['id'], $eventName, null, $e->getMessage());
                continue;
            }
            $curl = curl_init($targetUrl);
            if ($curl === false) {
                continue;
            }
            $options = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-MAMPSlate-Event: ' . $eventName,
                    'X-MAMPSlate-Signature: sha256=' . hash_hmac('sha256', $payload, (string)$endpoint['signing_secret']),
                ],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_RESOLVE => [
                    $target['host'] . ':' . $target['port'] . ':'
                        . (str_contains($target['addresses'][0], ':') ? '[' . $target['addresses'][0] . ']' : $target['addresses'][0]),
                ],
            ];
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($curl, $options);
            curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            $summary = $error !== '' ? $error : ($status >= 200 && $status < 300 ? 'Delivered.' : 'Endpoint returned HTTP ' . $status . '.');
            $this->webhooks->recordDelivery((int)$endpoint['id'], $eventName, $status ?: null, $summary);
        }
    }
}
