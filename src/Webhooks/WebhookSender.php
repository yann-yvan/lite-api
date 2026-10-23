<?php

namespace Nycorp\LiteApi\Webhooks;

use Illuminate\Support\Facades\Http;
use Nycorp\LiteApi\Webhooks\Events\WebhookDeliveryFailed;
use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;
use Throwable;

/**
 * One delivery attempt, recorded on the delivery and on its endpoint.
 */
final class WebhookSender
{
    public static function attempt(WebhookDelivery $delivery): AttemptResult
    {
        $endpoint = $delivery->endpoint;

        if ($endpoint === null || $endpoint->trashed() || !$endpoint->is_enabled) {
            self::abandon($delivery, 'The endpoint is disabled or deleted');

            return AttemptResult::ABANDONED;
        }

        if ($reason = WebhookUrlGuard::check($endpoint->url)) {
            $delivery->recordAttempt(false, null, null, null, $reason);
            self::abandon($delivery, $reason);

            return AttemptResult::ABANDONED;
        }

        $started = hrtime(true);

        try {
            $response = Http::timeout((int)config('lite-api.webhooks.timeout', 10))
                // A redirect could lead to an internal address the guard never checked
                ->withoutRedirecting()
                ->withHeaders(WebhookSigner::headers($delivery, $endpoint->secret, $endpoint->api_version))
                ->withBody($delivery->payload, 'application/json')
                ->post($endpoint->url);

            $succeeded = $response->successful();
            $status    = $response->status();
            $body      = $response->body();
            $error     = $succeeded ? null : "HTTP $status";
        } catch (Throwable $exception) {
            $succeeded = false;
            $status    = null;
            $body      = null;
            $error     = $exception->getMessage();
        }

        $delivery->recordAttempt($succeeded, $status, $body, (int)((hrtime(true) - $started) / 1_000_000), $error);

        if ($succeeded) {
            $endpoint->recordSuccess();

            return AttemptResult::SUCCEEDED;
        }

        $endpoint->recordFailure($error);

        return AttemptResult::RETRY;
    }

    public static function abandon(WebhookDelivery $delivery, ?string $error = null): void
    {
        $delivery->markFailed($error);

        event(new WebhookDeliveryFailed($delivery));
    }
}
