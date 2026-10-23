<?php

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Nycorp\LiteApi\Tests\Fixtures\Company;
use Nycorp\LiteApi\Tests\Fixtures\OrderPaid;
use Nycorp\LiteApi\Webhooks\Events\WebhookDeliveryFailed;
use Nycorp\LiteApi\Webhooks\Events\WebhookEndpointDisabled;
use Nycorp\LiteApi\Webhooks\Jobs\DeliverWebhookJob;
use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;
use Nycorp\LiteApi\Webhooks\WebhookSigner;
use Nycorp\LiteApi\Webhooks\Webhooks;

beforeEach(function () {
    $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/webhooks');
    Company::createTable();

    config(['lite-api.webhooks.allow_private_urls' => true, 'lite-api.webhooks.header_prefix' => 'X-Shortext']);

    $this->company = Company::create(['name' => 'Acme']);
    $this->other   = Company::create(['name' => 'Other']);

    $this->endpoint = fn(array $attributes = [], ?Company $owner = null) => ($owner ?? $this->company)->webhookEndpoints()->create($attributes + [
            'url'    => 'https://client.test/hook',
            'events' => ['order.*'],
        ]);
});

/**
 * Job whose attempt number is controlled and whose release() is recorded
 */
function deliveryJob(WebhookDelivery $delivery, int $attempt): DeliverWebhookJob
{
    return new class($delivery->id, $attempt) extends DeliverWebhookJob {
        public ?int $released = null;

        public function __construct(int $deliveryId, private readonly int $attempt)
        {
            parent::__construct($deliveryId);
        }

        public function attempts(): int
        {
            return $this->attempt;
        }

        public function release($delay = 0): void
        {
            $this->released = $delay;
        }
    };
}

it('fans out to the subscribed, matching and enabled endpoints of the owner only', function () {
    Queue::fake();

    $v1      = ($this->endpoint)(['api_version' => 'v1.0']);
    $v2      = ($this->endpoint)(['api_version' => 'v2.0', 'events' => ['order.paid']]);
    $v2Again = ($this->endpoint)(['api_version' => 'v2.0', 'events' => ['*'], 'filters' => ['shop' => 'S1']]);
    ($this->endpoint)(['events' => ['invoice.*']]);
    ($this->endpoint)(['filters' => ['shop' => 'S2']]);
    ($this->endpoint)(['is_enabled' => false]);
    ($this->endpoint)([], $this->other);

    $deliveries = Webhooks::fanOut(new OrderPaid($this->company));

    expect($deliveries->pluck('webhook_endpoint_id')->all())->toBe([$v1->id, $v2->id, $v2Again->id])
        ->and($deliveries->pluck('event_id')->unique())->toHaveCount(1)
        ->and(json_decode($deliveries[0]->payload, true))->toMatchArray(['type' => 'order.paid', 'api_version' => 'v1.0', 'data' => ['amount' => 10]])
        ->and(json_decode($deliveries[1]->payload, true)['data'])->toBe(['amount' => ['value' => 10, 'currency' => 'XAF']])
        ->and($deliveries[2]->payload)->toBe($deliveries[1]->payload);

    Queue::assertPushedOn('webhooks', DeliverWebhookJob::class);
    Queue::assertPushed(DeliverWebhookJob::class, 3);
});

it('announces nothing from a rolled back transaction', function () {
    Queue::fake();
    ($this->endpoint)();

    try {
        DB::transaction(function () {
            Webhooks::dispatch(new OrderPaid($this->company));
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(WebhookDelivery::count())->toBe(0);

    DB::transaction(fn() => Webhooks::dispatch(new OrderPaid($this->company)));

    expect(WebhookDelivery::count())->toBe(1);
});

it('delivers a signed payload the receiver can verify', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('ok')]);
    $endpoint = ($this->endpoint)(['api_version' => 'v2.0']);
    $delivery = Webhooks::fanOut(new OrderPaid($this->company))->first();

    deliveryJob($delivery, 1)->handle();

    Http::assertSent(function (ClientRequest $request) use ($endpoint, $delivery) {
        return $request->url() === 'https://client.test/hook'
            && $request->body() === $delivery->payload
            && $request->header('X-Shortext-Event')[0] === 'order.paid'
            && $request->header('X-Shortext-Delivery')[0] === $delivery->uuid
            && $request->header('X-Shortext-Api-Version')[0] === 'v2.0'
            && WebhookSigner::verify($endpoint->secret, $request->header('X-Shortext-Timestamp')[0], $request->body(), $request->header('X-Shortext-Signature')[0]);
    });

    expect($delivery->refresh())->status->toBe('succeeded')->attempts->toBe(1)->response_status->toBe(200)
        ->and($endpoint->refresh()->last_success_at)->not->toBeNull();
});

it('retries with the configured backoff then gives up', function () {
    Queue::fake();
    Event::fake([WebhookDeliveryFailed::class]);
    Http::fake(['*' => Http::response('down', 503)]);
    ($this->endpoint)();
    $delivery = Webhooks::fanOut(new OrderPaid($this->company))->first();

    $job = deliveryJob($delivery, 1);
    $job->handle();

    expect($job->released)->toBe(10)
        ->and($delivery->refresh())->status->toBe('pending')->error->toBe('HTTP 503')->next_retry_at->not->toBeNull();

    $job = deliveryJob($delivery, 7);
    $job->handle();
    expect($job->released)->toBe(43200);

    $job = deliveryJob($delivery, 8);
    $job->handle();

    expect($job->released)->toBeNull()
        ->and($delivery->refresh())->status->toBe('failed')->attempts->toBe(3);
    Event::assertDispatched(WebhookDeliveryFailed::class);
});

it('disables an endpoint failing for too long', function () {
    Queue::fake();
    Event::fake([WebhookEndpointDisabled::class]);
    $status = 500;
    Http::fake(function () use (&$status) {
        return Http::response('', $status);
    });
    $endpoint = ($this->endpoint)();
    $endpoint->forceFill(['failing_since' => now()->subHours(73)])->save();

    deliveryJob(Webhooks::fanOut(new OrderPaid($this->company))->first(), 1)->handle();

    expect($endpoint->refresh())->is_enabled->toBeFalse()->disabled_reason->toContain('72h');
    Event::assertDispatched(WebhookEndpointDisabled::class);

    // A success resets the failure streak once re-enabled
    $status = 200;
    $endpoint->enable();
    deliveryJob(Webhooks::fanOut(new OrderPaid($this->company))->first(), 1)->handle();

    expect($endpoint->refresh())->failing_since->toBeNull()->is_enabled->toBeTrue();
});

it('abandons a delivery whose URL now points to a private address', function () {
    Queue::fake();
    Http::fake();
    ($this->endpoint)(['url' => 'https://127.0.0.1/hook']);
    $delivery = Webhooks::fanOut(new OrderPaid($this->company))->first();

    config(['lite-api.webhooks.allow_private_urls' => false]);
    deliveryJob($delivery, 1)->handle();

    Http::assertNothingSent();
    expect($delivery->refresh())->status->toBe('failed')->error->toContain('private');
});

it('pings an endpoint once, without retry', function () {
    Queue::fake();
    $endpoint = ($this->endpoint)();

    $status = 500;
    Http::fake(function () use (&$status) {
        return Http::response('', $status);
    });
    $failed = Webhooks::ping($endpoint);

    $status    = 200;
    $succeeded = Webhooks::ping($endpoint);

    expect($failed)->status->toBe('failed')->event_type->toBe('webhook.test')
        ->and($succeeded->status)->toBe('succeeded');
    Queue::assertNothingPushed();
});

it('redelivers an event under the same event id', function () {
    Queue::fake();
    ($this->endpoint)();
    $original = Webhooks::fanOut(new OrderPaid($this->company))->first();

    $copy = Webhooks::redeliver($original);

    expect($copy)->event_id->toBe($original->event_id)->payload->toBe($original->payload)
        ->and($copy->uuid)->not->toBe($original->uuid);
    Queue::assertPushed(DeliverWebhookJob::class, 2);
});

it('prunes deliveries past the retention period', function () {
    Queue::fake();
    ($this->endpoint)();
    $old = Webhooks::fanOut(new OrderPaid($this->company))->first();
    $old->forceFill(['created_at' => now()->subDays(31)])->save();
    Webhooks::fanOut(new OrderPaid($this->company));

    expect((new WebhookDelivery())->prunable()->count())->toBe(1);
});
