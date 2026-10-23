<?php

namespace Nycorp\LiteApi\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;

/**
 * The endpoint kept failing and was disabled: notify its owner.
 */
class WebhookEndpointDisabled
{
    use Dispatchable;

    public function __construct(public readonly WebhookEndpoint $endpoint)
    {
    }
}
