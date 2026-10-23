<?php

use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;
use Nycorp\LiteApi\Webhooks\WebhookSigner;
use Nycorp\LiteApi\Webhooks\WebhookUrlGuard;

it('matches exact, prefix wildcard and catch-all subscriptions', function () {
    $endpoint = new WebhookEndpoint(['events' => ['message.*', 'ticket.created']]);

    expect($endpoint->subscribesTo('message.received'))->toBeTrue()
        ->and($endpoint->subscribesTo('ticket.created'))->toBeTrue()
        ->and($endpoint->subscribesTo('ticket.closed'))->toBeFalse()
        ->and($endpoint->subscribesTo('messages.x'))->toBeFalse()
        ->and((new WebhookEndpoint(['events' => ['*']]))->subscribesTo('anything'))->toBeTrue();
});

it('restricts an endpoint to the event context of its filters', function () {
    $endpoint = new WebhookEndpoint(['filters' => ['app' => 'AGI-1']]);

    expect($endpoint->matches(['app' => 'AGI-1', 'channel' => 'whatsapp']))->toBeTrue()
        ->and($endpoint->matches(['app' => 'AGI-2']))->toBeFalse()
        ->and($endpoint->matches([]))->toBeFalse()
        ->and((new WebhookEndpoint())->matches([]))->toBeTrue();
});

it('refuses non public webhook URLs', function () {
    config(['lite-api.webhooks.allow_private_urls' => false, 'lite-api.webhooks.require_https' => true]);

    expect(WebhookUrlGuard::check('http://93.184.216.34/hook'))->toBe('The URL must use HTTPS')
        ->and(WebhookUrlGuard::check('ftp://example.com'))->not->toBeNull()
        ->and(WebhookUrlGuard::check('https://127.0.0.1/hook'))->toContain('private')
        ->and(WebhookUrlGuard::check('https://10.0.0.5/hook'))->toContain('private')
        ->and(WebhookUrlGuard::check('https://169.254.169.254/latest/meta-data'))->toContain('private')
        ->and(WebhookUrlGuard::check('https://[::1]/hook'))->toContain('private')
        ->and(WebhookUrlGuard::check('https://93.184.216.34/hook'))->toBeNull();

    config(['lite-api.webhooks.allow_private_urls' => true]);
    expect(WebhookUrlGuard::check('https://127.0.0.1/hook'))->toBeNull();
});

it('verifies signatures and rejects tampering or stale timestamps', function () {
    $timestamp = (string)time();
    $signature = WebhookSigner::signature('whsec_x', $timestamp, '{"a":1}');

    expect(WebhookSigner::verify('whsec_x', $timestamp, '{"a":1}', $signature))->toBeTrue()
        ->and(WebhookSigner::verify('whsec_x', $timestamp, '{"a":2}', $signature))->toBeFalse()
        ->and(WebhookSigner::verify('whsec_y', $timestamp, '{"a":1}', $signature))->toBeFalse();

    $old = (string)(time() - 600);
    expect(WebhookSigner::verify('whsec_x', $old, '{"a":1}', WebhookSigner::signature('whsec_x', $old, '{"a":1}')))->toBeFalse();
});
