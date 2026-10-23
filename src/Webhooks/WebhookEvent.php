<?php

namespace Nycorp\LiteApi\Webhooks;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something that happened to an owner (company...) and is pushed to its webhook endpoints.
 *
 * class MessageReceived extends WebhookEvent
 * {
 *     public function __construct(private Message $message) {}
 *     public function type(): string { return 'message.received'; }
 *     public function owner(): Model { return $this->message->conversation->company; }
 *     public function context(): array { return ['app' => $this->message->app->hashid]; }
 *     public function data(?string $apiVersion): array { return MessagePresenter::present($this->message); }
 * }
 *
 * Webhooks::dispatch(new MessageReceived($message));
 */
abstract class WebhookEvent
{
    private ?string          $id         = null;
    private ?CarbonImmutable $occurredAt = null;

    abstract public function type(): string;

    abstract public function owner(): Model;

    /**
     * Payload for the API version the endpoint subscribed with (null: no version pinned).
     * A released version is frozen: change the payload of a new version only.
     */
    abstract public function data(?string $apiVersion): array;

    /**
     * Attributes endpoints can restrict themselves to through their filters, e.g. ['app' => 'AGI-XXXX']
     */
    public function context(): array
    {
        return [];
    }

    public function id(): string
    {
        return $this->id ??= 'evt_' . Str::ulid();
    }

    public function occurredAt(): CarbonImmutable
    {
        return $this->occurredAt ??= CarbonImmutable::now();
    }
}
