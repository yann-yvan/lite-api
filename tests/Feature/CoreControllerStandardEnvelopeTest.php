<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Nycorp\LiteApi\Auth\Actor;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Response\Envelopes\StandardEnvelope;
use Nycorp\LiteApi\Tests\Fixtures\Company;
use Nycorp\LiteApi\Tests\Fixtures\Widget;
use Nycorp\LiteApi\Tests\Fixtures\WidgetController;
use Nycorp\LiteApi\Versioning\VersionRegistry;

// Same REST resource as CoreControllerResourceTest, served by a version using the Standard envelope:
// add() and save() read their own response back, which must work whatever the envelope.
beforeEach(function () {
    config(['lite-api.versions' => ['v2.0' => ['envelope' => StandardEnvelope::class]]]);
    app()->forgetInstance(VersionRegistry::class);

    Company::createTable();
    Widget::createTable();
    $this->acme = Company::create(['name' => 'Acme']);

    LiteApi::resolveActorUsing(static function (Request $request): ?Actor {
        $company = Company::find($request->header('X-Company'));

        return $company ? new Actor($company, null, static fn() => true) : null;
    });

    LiteApi::version('v2.0', fn() => WidgetController::expose('widgets', softDelete: true, rest: true));

    $this->as = fn() => $this->withHeaders(['X-Company' => $this->acme->id]);
});

afterEach(fn() => LiteApi::resolveActorUsing(null));

it('creates through add() and answers in the Standard envelope', function () {
    $created = ($this->as)()->postJson('v2.0/widgets', ['name' => 'Gear'])
        ->assertOk()
        ->assertJsonMissingPath('success')
        ->json('data');

    expect($created['name'])->toBe('Gear')->and(Widget::count())->toBe(1);

    ($this->as)()->getJson("v2.0/widgets/{$created['id']}")->assertOk()->assertJsonPath('data.name', 'Gear');
    ($this->as)()->getJson('v2.0/widgets')->assertOk()->assertJsonPath('data.total', 1);
    ($this->as)()->patchJson("v2.0/widgets/{$created['id']}", ['name' => 'Cog'])->assertOk()->assertJsonPath('data.name', 'Cog');
    ($this->as)()->deleteJson("v2.0/widgets/{$created['id']}")->assertOk();
    ($this->as)()->patchJson("v2.0/widgets/{$created['id']}/restore")->assertOk()->assertJsonPath('data.name', 'Cog');
});

it('returns validation errors and business refusals as Standard errors, without creating anything', function () {
    ($this->as)()->postJson('v2.0/widgets', [])
        ->assertStatus(422)
        ->assertJsonPath('error.type', 'REQUEST_VALIDATION_ERROR')
        ->assertJsonPath('error.details.name.0', 'The name field is required.');

    ($this->as)()->postJson('v2.0/widgets', ['name' => 'forbidden'])
        ->assertStatus(403)
        ->assertJsonPath('error.type', 'REQUEST_NOT_AUTHORIZED')
        ->assertJsonPath('error.message', 'Name refused');

    expect(Widget::count())->toBe(0);
});
