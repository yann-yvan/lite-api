<?php

namespace Nycorp\LiteApi\Webhooks;

use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;

/**
 * Receiver side verification:
 *   expected = "sha256=" . hex(hmac_sha256(secret, "{timestamp header}.{raw body}"))
 *   compare with the signature header in constant time, reject timestamps older than 5 minutes.
 */
final class WebhookSigner
{
    public static function signature(string $secret, string $timestamp, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', "$timestamp.$body", $secret);
    }

    public static function headers(WebhookDelivery $delivery, string $secret, ?string $apiVersion = null, ?int $timestamp = null): array
    {
        $prefix    = config('lite-api.webhooks.header_prefix', 'X-Webhook');
        $timestamp = (string)($timestamp ?? time());

        return array_filter([
            "$prefix-Event"       => $delivery->event_type,
            "$prefix-Delivery"    => $delivery->uuid,
            "$prefix-Timestamp"   => $timestamp,
            "$prefix-Signature"   => self::signature($secret, $timestamp, $delivery->payload),
            "$prefix-Api-Version" => $apiVersion,
            'User-Agent'          => config('app.name', 'LiteApi') . '-Webhooks/1.0',
        ], static fn($value) => $value !== null);
    }

    public static function verify(string $secret, string $timestamp, string $body, string $signature, int $toleranceSeconds = 300): bool
    {
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(self::signature($secret, $timestamp, $body), $signature);
    }
}
