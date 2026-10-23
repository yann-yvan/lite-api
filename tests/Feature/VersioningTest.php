<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Nycorp\LiteApi\Events\ApiVersionUsed;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Response\Envelopes\StandardEnvelope;
use Nycorp\LiteApi\Tests\Fixtures\DenyMiddleware;
use Nycorp\LiteApi\Tests\Fixtures\Responder;
use Nycorp\LiteApi\Versioning\VersionRegistry;

beforeEach(function () {
    config(['lite-api.versions' => [
        'v0.9' => ['sunset_at' => '2020-01-01'],
        'v1.0' => ['deprecated_at' => '2099-01-01', 'sunset_at' => '2099-06-01', 'changelog' => 'https://docs.test/changelog#v1.0'],
        'v2.0' => ['envelope' => StandardEnvelope::class],
    ]]);
    app()->forgetInstance(VersionRegistry::class);

    $routes = function () {
        Route::get('ok', fn() => Responder::liteResponse(ResponseCode::REQUEST_SUCCESS, ['id' => 1]));
        Route::get('invalid', fn() => Responder::liteResponse(ResponseCode::REQUEST_VALIDATION_ERROR, ['name' => ['required']], 'The name field is required.'));
    };

    foreach (['v0.9', 'v1.0', 'v2.0'] as $version) {
        LiteApi::version($version, $routes);
    }

    Route::get('legacy/ok', fn() => Responder::liteResponse(ResponseCode::REQUEST_SUCCESS, ['id' => 1]));

    Route::middleware(DenyMiddleware::class)->group(
        fn() => LiteApi::version('v2.0', fn() => Route::get('guarded', fn() => 'never'))
    );

    LiteApi::versionsRoute();
});

it('keeps the historical response byte for byte outside versioned routes', function () {
    $response = $this->getJson('legacy/ok');

    $response->assertOk()->assertHeaderMissing('X-Api-Version');

    expect($response->getContent())->toBe(json_encode(
        ['success' => true, 'message' => 'Request success.', 'code' => 1000, 'body' => ['id' => 1]],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ));
});

it('serves a legacy version with its deprecation notice', function () {
    $this->getJson('v1.0/ok')
        ->assertOk()
        ->assertExactJson(['success' => true, 'message' => 'Request success.', 'code' => 1000, 'body' => ['id' => 1]])
        ->assertHeader('X-Api-Version', 'v1.0')
        ->assertHeader('Deprecation', '@' . CarbonImmutable::parse('2099-01-01')->getTimestamp())
        ->assertHeader('Sunset', CarbonImmutable::parse('2099-06-01')->toRfc7231String())
        ->assertHeader('Link', '<https://docs.test/changelog#v1.0>; rel="deprecation"');

    $this->getJson('v1.0/invalid')
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 1002);
});

it('uses real HTTP statuses with the standard envelope', function () {
    $this->getJson('v2.0/ok')
        ->assertOk()
        ->assertExactJson(['data' => ['id' => 1]])
        ->assertHeader('X-Api-Version', 'v2.0')
        ->assertHeaderMissing('Deprecation');

    $this->getJson('v2.0/invalid')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 1002)
        ->assertJsonPath('error.type', 'REQUEST_VALIDATION_ERROR')
        ->assertJsonPath('error.message', 'The name field is required.')
        ->assertJsonPath('error.details', ['name' => ['required']]);
});

it('answers 410 on a sunset version', function () {
    $this->getJson('v0.9/ok')
        ->assertStatus(410)
        ->assertJsonPath('code', 1200)
        ->assertJsonPath('body.latest', 'v2.0')
        ->assertHeader('X-Api-Version', 'v0.9');
});

it('applies the version envelope to responses built before the version middleware', function () {
    $this->getJson('v2.0/guarded')
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'TOKEN_NOT_FOUND');
});

it('reports version usage', function () {
    Event::fake([ApiVersionUsed::class]);

    $this->getJson('v2.0/invalid');

    Event::assertDispatched(ApiVersionUsed::class, fn(ApiVersionUsed $event) => $event->version->name === 'v2.0' && $event->status === 422);
});

it('lists the declared versions', function () {
    $this->getJson('versions')
        ->assertOk()
        ->assertJsonPath('body.latest', 'v2.0')
        ->assertJsonPath('body.versions.0.status', 'sunset')
        ->assertJsonPath('body.versions.1.status', 'active')
        ->assertJsonPath('body.versions.1.deprecated_at', '2099-01-01')
        ->assertJsonPath('body.versions.2.version', 'v2.0');
});

it('refuses to register an undeclared version', function () {
    LiteApi::version('v9.9', fn() => null);
})->throws(InvalidArgumentException::class);
