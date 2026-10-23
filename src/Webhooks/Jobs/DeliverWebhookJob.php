<?php

namespace Nycorp\LiteApi\Webhooks\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Nycorp\LiteApi\Webhooks\AttemptResult;
use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;
use Nycorp\LiteApi\Webhooks\WebhookSender;
use Throwable;

/**
 * Delivers one webhook, retrying with config lite-api.webhooks.backoff.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public readonly int $deliveryId)
    {
        // First attempt + one per backoff step; retries are driven by release()
        $this->tries = count(config('lite-api.webhooks.backoff', [])) + 1;
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if ($delivery === null || $delivery->isFinal()) {
            return;
        }

        if (WebhookSender::attempt($delivery) !== AttemptResult::RETRY) {
            return;
        }

        $delay = config('lite-api.webhooks.backoff', [])[$this->attempts() - 1] ?? null;

        if ($delay === null) {
            WebhookSender::abandon($delivery);

            return;
        }

        $delivery->forceFill(['next_retry_at' => now()->addSeconds($delay)])->save();

        $this->release($delay);
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if ($delivery !== null && !$delivery->isFinal()) {
            WebhookSender::abandon($delivery, $exception?->getMessage());
        }
    }
}
