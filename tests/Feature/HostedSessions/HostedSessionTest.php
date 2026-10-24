<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\HostedSessions\Events\HostedSessionCompleted;
use Nycorp\LiteApi\HostedSessions\Events\HostedSessionFailed;
use Nycorp\LiteApi\HostedSessions\HostedSessions;
use Nycorp\LiteApi\HostedSessions\Http\HostedSessionController;
use Nycorp\LiteApi\HostedSessions\Middleware\ResolveHostedSession;
use Nycorp\LiteApi\HostedSessions\Models\HostedSession;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Tests\Fixtures\Company;
use Nycorp\LiteApi\Versioning\VersionRegistry;

beforeEach(function () {
    $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/hosted-sessions');
    Company::createTable();

    config([
        'lite-api.versions'                 => ['v2.0' => []],
        'lite-api.hosted_sessions.purposes' => ['connect' => 'Connect a channel', 'build' => 'Build a template'],
    ]);
    app()->forgetInstance(VersionRegistry::class);

    LiteApi::version('v2.0', fn() => LiteApi::hostedSessionRoutes());

    // The page of the application
    Route::get('connect/{token}', static function (Request $request) {
        $session = ResolveHostedSession::session($request);
        if ($session->isUsable()) {
            $session->markOpened();
        }

        return response($session->state());
    })->middleware(ResolveHostedSession::ALIAS . ':connect')->name('lite-api.hosted-session');
    // Routes added after boot: refresh the name index (an application defines it in its route files)
    Route::getRoutes()->refreshNameLookups();

    $this->company = Company::create(['name' => 'Acme']);
    $this->other   = Company::create(['name' => 'Other']);
    $this->actingAs($this->company);
});

afterEach(fn() => HostedSessionController::contextUsing('connect', null));

it('creates a session whose URL is only given once and holds a secret token', function () {
    $created = $this->postJson('v2.0/hosted-sessions', ['purpose' => 'connect', 'return_url' => 'https://client.test/back', 'context' => ['agent' => 'A1']])
        ->assertJsonPath('success', true)
        ->assertJsonPath('body.status', 'pending')
        ->assertJsonPath('body.context.agent', 'A1')
        ->json('body');

    expect($created['url'])->toContain('/connect/hs_');

    $token = basename($created['url']);
    expect(HostedSession::query()->value('token_hash'))->toBe(hash('sha256', $token))
        ->and(HostedSession::query()->where('token_hash', $token)->exists())->toBeFalse();

    $this->getJson("v2.0/hosted-sessions/{$created['id']}")
        ->assertJsonPath('body.id', $created['id'])
        ->assertJsonMissingPath('body.url');
});

it('validates the purpose, the return URL and the context of the purpose', function () {
    $this->postJson('v2.0/hosted-sessions', ['purpose' => 'unknown'])->assertJsonPath('code', 1002);
    $this->postJson('v2.0/hosted-sessions', ['purpose' => 'connect', 'return_url' => 'http://client.test/back'])->assertJsonPath('code', 1002);

    HostedSessionController::contextUsing('connect', static function (Request $request, $owner) {
        if (!$request->filled('context.agent')) {
            throw new LiteResponseException(ResponseCode::REQUEST_VALIDATION_ERROR, 'context.agent is required');
        }

        return ['agent' => $request->input('context.agent'), 'owner' => $owner->name];
    });

    $this->postJson('v2.0/hosted-sessions', ['purpose' => 'connect'])
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'context.agent is required');

    $this->postJson('v2.0/hosted-sessions', ['purpose' => 'connect', 'context' => ['agent' => 'A1', 'other' => 'dropped']])
        ->assertJsonPath('body.context', ['agent' => 'A1', 'owner' => 'Acme']);
});

it('opens the page once usable, then shows its state when finished or expired', function () {
    [$session, $url] = HostedSessions::start($this->company, 'connect', returnUrl: 'https://client.test/back?x=1');

    $this->get($url)->assertOk()->assertSee('opened')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    expect($session->fresh()->status)->toBe(HostedSession::OPENED);

    Event::fake([HostedSessionCompleted::class]);
    $session->fresh()->complete(['channel' => 'whatsapp:123']);
    Event::assertDispatched(HostedSessionCompleted::class, fn($event) => $event->session->is($session));

    $this->get($url)->assertSee('completed');
    expect($session->fresh()->returnUrl())->toBe("https://client.test/back?x=1&session_id={$session->uuid}&status=completed");

    [$late, $lateUrl] = HostedSessions::start($this->company, 'connect', ttlMinutes: 5);
    $this->travel(6)->minutes();
    $this->get($lateUrl)->assertSee('expired');
    expect($late->fresh()->isUsable())->toBeFalse();
});

it('refuses an unknown token and a token of another purpose', function () {
    $this->get('connect/hs_' . str_repeat('x', 48))->assertNotFound();
    $this->get('connect/not-a-token')->assertNotFound();

    [, $url] = HostedSessions::start($this->company, 'build');
    $this->get($url)->assertNotFound();
});

it('fails with an error and notifies', function () {
    Event::fake([HostedSessionFailed::class]);
    [$session] = HostedSessions::start($this->company, 'connect');

    $session->fail('Meta refused the code');

    expect($session->fresh())->status->toBe(HostedSession::FAILED)->error->toBe('Meta refused the code');
    Event::assertDispatched(HostedSessionFailed::class);
});

it('keeps sessions to their owner and cancels only open ones', function () {
    [$mine]   = HostedSessions::start($this->company, 'connect');
    [$theirs] = HostedSessions::start($this->other, 'connect');

    $this->getJson("v2.0/hosted-sessions/{$theirs->uuid}")->assertJsonPath('code', 1007);
    $this->getJson('v2.0/hosted-sessions')->assertJsonPath('body.total', 1)->assertJsonPath('body.data.0.id', $mine->uuid);
    $this->getJson('v2.0/hosted-sessions/purposes')->assertJsonPath('body.connect', 'Connect a channel');

    $this->deleteJson("v2.0/hosted-sessions/{$mine->uuid}")->assertJsonPath('body.status', 'cancelled');
    $this->deleteJson("v2.0/hosted-sessions/{$mine->uuid}")->assertJsonPath('success', false);
    $this->deleteJson("v2.0/hosted-sessions/{$theirs->uuid}")->assertJsonPath('code', 1007);
});

it('caps the lifetime', function () {
    config(['lite-api.hosted_sessions.max_ttl_minutes' => 30]);
    [$session] = HostedSessions::start($this->company, 'connect', ttlMinutes: 600);

    expect((int)round(now()->diffInMinutes($session->expires_at)))->toBe(30);
});
