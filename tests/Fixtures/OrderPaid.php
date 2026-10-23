<?php

namespace Nycorp\LiteApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Nycorp\LiteApi\Webhooks\WebhookEvent;

class OrderPaid extends WebhookEvent
{
    public function __construct(private readonly Company $company, private readonly string $shop = 'S1')
    {
    }

    public function type(): string
    {
        return 'order.paid';
    }

    public function owner(): Model
    {
        return $this->company;
    }

    public function context(): array
    {
        return ['shop' => $this->shop];
    }

    public function data(?string $apiVersion): array
    {
        // Payload changed in v2.0: endpoints pinned to v1.0 keep the old shape
        return $apiVersion === 'v1.0' ? ['amount' => 10] : ['amount' => ['value' => 10, 'currency' => 'XAF']];
    }
}
