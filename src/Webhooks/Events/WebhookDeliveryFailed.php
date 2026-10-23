<?php

namespace Nycorp\LiteApi\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;

/**
 * Every retry is exhausted (or the delivery was abandoned): the event did not reach the endpoint.
 */
class WebhookDeliveryFailed
{
    use Dispatchable;

    public function __construct(public readonly WebhookDelivery $delivery)
    {
    }
}
