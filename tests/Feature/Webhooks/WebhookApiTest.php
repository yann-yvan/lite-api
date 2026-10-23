<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Tests\Fixtures\Company;
use Nycorp\LiteApi\Tests\Fixtures\OrderPaid;
use Nycorp\LiteApi\Versioning\VersionRegistry;
use Nycorp\LiteApi\Webhooks\Jobs\DeliverWebhookJob;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;
use Nycorp\LiteApi\Webhooks\Webhooks;
use OpenApi\Generator;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/webhooks');
    Company::createTable();

    config([
        'lite-api.versions'                  => ['v2.0' => []],
        'lite-api.webhooks.allow_private_urls' => true,
        'lite-api.webhooks.events'           => ['order.paid' => 'An order was paid', 'order.refunded' => 'An order was refunded'],
    ]);
    app()->forgetInstance(VersionRegistry::class);

    LiteApi::version('v2.0', fn() => LiteApi::webhookRoutes());

    $this->company = Company::create(['name' => 'Acme']);
    $this->other   = Company::create(['name' => 'Other']);
    $this->actingAs($this->company);
});

it('creates an endpoint pinned to the calling API version and shows the secret once', function () {
    $created = $this->postJson('v2.0/webhooks', ['url' => 'https://client.test/hook', 'events' => ['order.*'], 'filters' => ['shop' => 'S1']])
        ->assertJsonPath('success', true)
        ->assertJsonPath('body.api_version', 'v2.0')
        ->assertJsonPath('body.events', ['order.*'])
        ->json('body');

    expect($created['secret'])->toStartWith('whsec_');

    $this->getJson("v2.0/webhooks/{$created['id']}")
        ->assertJsonPath('body.id', $created['id'])
        ->assertJsonMissingPath('body.secret');
});

it('validates events against the catalog and refuses private URLs', function () {
    $this->postJson('v2.0/webhooks', ['url' => 'https://client.test/hook', 'events' => ['invoice.paid']])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 1002)
        ->assertJsonPath('body', fn(array $errors) => str_contains($errors['events.0'][0], 'Unknown event invoice.paid'));

    config(['lite-api.webhooks.allow_private_urls' => false]);

    $this->postJson('v2.0/webhooks', ['url' => 'https://192.168.1.10/hook', 'events' => ['order.paid']])
        ->assertJsonPath('code', 1002)
        ->assertJsonPath('message', 'The host 192.168.1.10 resolves to a private or reserved address');
});

it('only exposes the endpoints of the authenticated owner', function () {
    $mine   = $this->company->webhookEndpoints()->create(['url' => 'https://client.test/a', 'events' => ['*']]);
    $theirs = $this->other->webhookEndpoints()->create(['url' => 'https://client.test/b', 'events' => ['*']]);

    $this->getJson('v2.0/webhooks')->assertJsonCount(1, 'body')->assertJsonPath('body.0.id', $mine->uuid);
    $this->getJson("v2.0/webhooks/$theirs->uuid")->assertJsonPath('code', 1007);
    $this->deleteJson("v2.0/webhooks/$theirs->uuid")->assertJsonPath('code', 1007);

    expect($theirs->refresh()->trashed())->toBeFalse();
});

it('re-enables a disabled endpoint and rotates its secret', function () {
    $endpoint = $this->company->webhookEndpoints()->create(['url' => 'https://client.test/a', 'events' => ['*']]);
    $endpoint->disable('Failing for more than 72h');
    $endpoint->forceFill(['failing_since' => now()->subDays(4)])->save();

    $this->putJson("v2.0/webhooks/$endpoint->uuid", ['is_enabled' => true])
        ->assertJsonPath('body.is_enabled', true)
        ->assertJsonPath('body.disabled_reason', null)
        ->assertJsonPath('body.failing_since', null);

    $secret = $this->postJson("v2.0/webhooks/$endpoint->uuid/rotate-secret")->json('body.secret');

    expect($secret)->toStartWith('whsec_')->not->toBe($endpoint->secret)
        ->and($endpoint->refresh()->secret)->toBe($secret);
});

it('tests an endpoint, lists its deliveries and redelivers one', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('pong')]);
    $endpoint = $this->company->webhookEndpoints()->create(['url' => 'https://client.test/a', 'events' => ['order.paid']]);

    $this->postJson("v2.0/webhooks/$endpoint->uuid/test")
        ->assertJsonPath('body.event_type', 'webhook.test')
        ->assertJsonPath('body.status', 'succeeded')
        ->assertJsonPath('body.response_body', 'pong');

    $delivery = Webhooks::fanOut(new OrderPaid($this->company))->first();

    $this->getJson("v2.0/webhooks/$endpoint->uuid/deliveries?status=pending")
        ->assertJsonPath('body.total', 1)
        ->assertJsonPath('body.data.0.id', $delivery->uuid)
        ->assertJsonPath('body.data.0.payload.type', 'order.paid');

    $this->postJson("v2.0/webhooks/$endpoint->uuid/deliveries/$delivery->uuid/retry")
        ->assertJsonPath('body.event_id', $delivery->event_id);

    Queue::assertPushed(DeliverWebhookJob::class, 2);
});

it('publishes the event catalog', function () {
    $this->getJson('v2.0/webhooks/events')
        ->assertJsonPath('body.0', ['type' => 'order.paid', 'description' => 'An order was paid'])
        ->assertJsonPath('body.2.type', 'webhook.test');
});

it('documents the webhook API in OpenAPI 3.1', function () {
    $openApi = Generator::scan([LiteApi::webhookDocs()], ['logger' => new NullLogger(), 'version' => '3.1.0']);
    $json    = json_decode($openApi->toJson(), true);

    expect(array_keys($json['paths']))->toContain('/webhooks', '/webhooks/{id}/test', '/webhooks/{id}/deliveries/{delivery}/retry')
        ->and(array_keys($json['components']['schemas']))->toContain('WebhookEndpoint', 'WebhookDelivery', 'WebhookEventPayload');
});
