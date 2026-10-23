<?php

namespace Nycorp\LiteApi\Webhooks\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;

/**
 * For the model owning webhook endpoints (company, account...)
 */
trait HasWebhookEndpoints
{
    public function webhookEndpoints(): MorphMany
    {
        return $this->morphMany(WebhookEndpoint::class, 'owner');
    }
}
