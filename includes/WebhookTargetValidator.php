<?php
declare(strict_types=1);

final class WebhookTargetValidator
{
    /**
     * Validate and resolve an HTTPS webhook target to public IP addresses.
     * The returned addresses can be pinned by the HTTP client to prevent DNS
     * rebinding between validation and connection.
     */
    public static function resolve(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Webhooks require an HTTPS endpoint.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Webhook URLs cannot contain credentials.');
        }

        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if ($host === '' || $host === 'localhost') {
            throw new InvalidArgumentException('Webhook endpoints must use a public host.');
        }
        $port = (int)($parts['port'] ?? 443);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Webhook endpoint port is invalid.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::dnsAddresses($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('Webhook endpoint host could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (!self::isPublicIp($address)) {
                throw new InvalidArgumentException('Webhook endpoints cannot resolve to private or reserved networks.');
            }
        }

        return ['host' => $host, 'port' => $port, 'addresses' => array_values(array_unique($addresses))];
    }

    public static function isPublicIp(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private static function dnsAddresses(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = (string)$record['ipv6'];
                }
            }
        }
        return $addresses;
    }
}
