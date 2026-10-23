<?php

namespace Nycorp\LiteApi\Webhooks;

/**
 * SSRF protection: a webhook URL must be public, it must never reach our own network.
 * Checked when the endpoint is saved and again before each delivery (DNS may change).
 */
final class WebhookUrlGuard
{
    /**
     * @return string|null why the URL is refused, null when it is acceptable
     */
    public static function check(string $url): ?string
    {
        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = trim($parts['host'] ?? '', '[]');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return 'The URL must be an absolute http(s) URL';
        }

        if ($scheme !== 'https' && config('lite-api.webhooks.require_https', true)) {
            return 'The URL must use HTTPS';
        }

        if (config('lite-api.webhooks.allow_private_urls', false)) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($ips === []) {
            return "The host $host cannot be resolved";
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return "The host $host resolves to a private or reserved address";
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips     = array_filter(array_map(static fn(array $record) => $record['ip'] ?? $record['ipv6'] ?? null, $records));

        return $ips !== [] ? array_values($ips) : (gethostbynamel($host) ?: []);
    }
}
