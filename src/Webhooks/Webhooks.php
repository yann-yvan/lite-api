<?php

namespace Nycorp\LiteApi\Webhooks;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nycorp\LiteApi\Webhooks\Jobs\DeliverWebhookJob;
use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;

final class Webhooks
{
    /**
     * Queue the event to every enabled endpoint of its owner that subscribed to it.
     * Inside a transaction, waits for the commit: nothing is announced that could be rolled back.
     */
    public static function dispatch(WebhookEvent $event): void
    {
        DB::afterCommit(static fn() => self::fanOut($event));
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    public static function fanOut(WebhookEvent $event): Collection
    {
        $owner = $event->owner();

        $endpoints = WebhookEndpoint::query()
            ->enabled()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->get()
            ->filter(static fn(WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event->type()) && $endpoint->matches($event->context()));

        // One payload per API version, shared by the endpoints pinned to it
        $bodies = [];

        return $endpoints->map(static function (WebhookEndpoint $endpoint) use ($event, &$bodies) {
            $delivery = self::record($endpoint, $event, $bodies[$endpoint->api_version ?? ''] ??= self::body($event, $endpoint->api_version));
            self::queue($delivery);

            return $delivery;
        })->values();
    }

    /**
     * Send webhook.test now and return the recorded delivery
     */
    public static function ping(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $event    = new PingWebhookEvent($endpoint);
        $delivery = self::record($endpoint, $event, self::body($event, $endpoint->api_version));

        WebhookSender::attempt($delivery);

        // A test is a one shot: never retried
        if (!$delivery->isFinal()) {
            $delivery->markFailed();
        }

        return $delivery->refresh();
    }

    /**
     * New delivery of the same event (same event id, so receivers can dedupe)
     */
    public static function redeliver(WebhookDelivery $delivery): WebhookDelivery
    {
        $copy = $delivery->endpoint->deliveries()->create([
            'event_id'   => $delivery->event_id,
            'event_type' => $delivery->event_type,
            'payload'    => $delivery->payload,
        ]);

        self::queue($copy);

        return $copy;
    }

    public static function body(WebhookEvent $event, ?string $apiVersion): string
    {
        return json_encode([
            'id'          => $event->id(),
            'type'        => $event->type(),
            'api_version' => $apiVersion,
            'created_at'  => $event->occurredAt()->toIso8601String(),
            'data'        => $event->data($apiVersion),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Subscribable events: config lite-api.webhooks.events + webhook.test
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return config('lite-api.webhooks.events', []) + [PingWebhookEvent::TYPE => 'Sent on demand to check an endpoint'];
    }

    private static function record(WebhookEndpoint $endpoint, WebhookEvent $event, string $body): WebhookDelivery
    {
        return $endpoint->deliveries()->create([
            'event_id'   => $event->id(),
            'event_type' => $event->type(),
            'payload'    => $body,
        ]);
    }

    private static function queue(WebhookDelivery $delivery): void
    {
        DeliverWebhookJob::dispatch($delivery->id)
            ->onConnection(config('lite-api.webhooks.connection'))
            ->onQueue(config('lite-api.webhooks.queue', 'webhooks'));
    }
}
