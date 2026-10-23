<?php

namespace Nycorp\LiteApi\Webhooks;

use Illuminate\Database\Eloquent\Model;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;

/**
 * webhook.test: sent on demand to check an endpoint
 */
class PingWebhookEvent extends WebhookEvent
{
    public const TYPE = 'webhook.test';

    public function __construct(private readonly WebhookEndpoint $endpoint)
    {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function owner(): Model
    {
        return $this->endpoint->owner;
    }

    public function data(?string $apiVersion): array
    {
        return [
            'endpoint_id' => $this->endpoint->uuid,
            'message'     => 'Webhook test: answer with a 2xx to acknowledge.',
        ];
    }
}
