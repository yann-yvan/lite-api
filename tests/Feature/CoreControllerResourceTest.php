<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Nycorp\LiteApi\Auth\Actor;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Middleware\RequirePermission;
use Nycorp\LiteApi\Tests\Fixtures\Company;
use Nycorp\LiteApi\Tests\Fixtures\Widget;
use Nycorp\LiteApi\Tests\Fixtures\WidgetController;

beforeEach(function () {
    Company::createTable();
    Widget::createTable();

    $this->acme  = Company::create(['name' => 'Acme']);
    $this->other = Company::create(['name' => 'Other']);

    // Actor resolved from a header, as an app would from a token: company + permissions
    LiteApi::resolveActorUsing(static function (Request $request): ?Actor {
        $company = Company::find($request->header('X-Company'));

        return $company ? new Actor($company, null, static fn(string $permission) => $permission !== 'denied') : null;
    });

    Route::middleware(RequirePermission::class . ':widgets.manage')->group(function () {
        WidgetController::expose('widgets', softDelete: true, routes: [['get', 'stats', 'stats']], rest: true);
    });
    Route::middleware(RequirePermission::class . ':denied')->group(fn() => Route::get('denied', fn() => 'never'));
    WidgetController::expose('legacy-widgets');

    $this->as = fn(Company $company) => $this->withHeaders(['X-Company' => $company->id]);
});

afterEach(fn() => LiteApi::resolveActorUsing(null));

it('exposes REST routes, custom routes first', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn($route) => implode('|', $route->methods()) . ' ' . $route->uri())->all();

    expect($uris)->toContain('GET|HEAD widgets', 'GET|HEAD widgets/{id}', 'POST widgets', 'PUT|PATCH widgets/{id}', 'DELETE widgets/{id}', 'PATCH widgets/{id}/restore', 'GET|HEAD widgets/stats')
        ->and($uris)->toContain('POST legacy-widgets/add', 'PUT legacy-widgets/update/{id}', 'GET|HEAD legacy-widgets/search/{id?}', 'DELETE legacy-widgets/delete/{id}');

    ($this->as)($this->acme)->getJson('widgets/stats')->assertJsonPath('body.count', 0);
});

it('creates, reads, updates and deletes through the presenter, scoped to the actor', function () {
    $created = ($this->as)($this->acme)->postJson('widgets', ['name' => 'Gear'])->assertJsonPath('success', true)->json('body');
    expect($created)->toBe(['id' => $created['id'], 'name' => 'Gear'])->and($created['id'])->toStartWith('WID-');

    // CREATE_ONLY: the same name creates another record
    ($this->as)($this->acme)->postJson('widgets', ['name' => 'Gear']);
    expect(Widget::count())->toBe(2);

    ($this->as)($this->acme)->getJson("widgets/{$created['id']}")->assertJsonPath('body.name', 'Gear');
    ($this->as)($this->acme)->getJson('widgets')->assertJsonPath('body.total', 2)->assertJsonPath('body.data.0', fn($row) => array_keys($row) === ['id', 'name']);
    ($this->as)($this->acme)->patchJson("widgets/{$created['id']}", ['name' => 'Cog'])->assertJsonPath('body', ['id' => $created['id'], 'name' => 'Cog']);
    ($this->as)($this->acme)->deleteJson("widgets/{$created['id']}")->assertJsonPath('body.name', 'Cog');

    expect(Widget::withTrashed()->find(substr($created['id'], 4))->trashed())->toBeTrue();
    ($this->as)($this->acme)->patchJson("widgets/{$created['id']}/restore")->assertJsonPath('body.name', 'Cog');
});

it('never reaches the records of another owner', function () {
    $theirs = Widget::create(['name' => 'Theirs', 'company_id' => $this->other->id]);

    foreach ([['getJson', []], ['patchJson', ['name' => 'Mine']], ['deleteJson', []]] as [$method, $body]) {
        ($this->as)($this->acme)->{$method}("widgets/WID-$theirs->id", $body)->assertJsonPath('code', 1007);
    }

    expect($theirs->refresh()->name)->toBe('Theirs');
});

it('turns business refusals into responses', function () {
    // create() refusing before any record exists used to crash on an undefined $model
    ($this->as)($this->acme)->postJson('widgets', ['name' => 'forbidden'])->assertJsonPath('code', 1005)->assertJsonPath('message', 'Name refused');

    $locked = Widget::create(['name' => 'locked', 'company_id' => $this->acme->id]);
    ($this->as)($this->acme)->patchJson("widgets/WID-$locked->id", ['name' => 'forbidden'])->assertJsonPath('message', 'Rename refused');
    ($this->as)($this->acme)->deleteJson("widgets/WID-$locked->id")->assertJsonPath('message', 'Locked widget');

    $gone = Widget::create(['name' => 'gone', 'company_id' => $this->acme->id]);
    $gone->delete();
    ($this->as)($this->acme)->patchJson("widgets/WID-$gone->id/restore")->assertJsonPath('message', 'Cannot restore');

    expect($locked->refresh()->trashed())->toBeFalse()->and($gone->refresh()->trashed())->toBeTrue();
});

it('reads perPage from the query string, capped', function () {
    foreach (range(1, 3) as $i) {
        Widget::create(['name' => "W$i", 'company_id' => $this->acme->id]);
    }

    ($this->as)($this->acme)->getJson('widgets?perPage=2')->assertJsonPath('body.per_page', 2)->assertJsonCount(2, 'body.data');
    ($this->as)($this->acme)->getJson('widgets?perPage=1000')->assertJsonPath('body.per_page', 100);

    foreach (['0', '-5', 'abc', ''] as $invalid) {
        ($this->as)($this->acme)->getJson("widgets?perPage=$invalid")->assertJsonPath('body.per_page', 50);
    }
});

it('requires an actor holding the permission', function () {
    $this->getJson('widgets')->assertJsonPath('code', 4);
    ($this->as)($this->acme)->getJson('denied')->assertJsonPath('code', 1005)->assertJsonPath('message', 'Missing the denied permission');
});

it('defaults the actor to the token owner and its abilities', function () {
    LiteApi::resolveActorUsing(null);
    $this->acme->forceFill([])->save();

    $request = Request::create('/');
    $request->setUserResolver(fn() => $this->acme);
    $actor = LiteApi::actor($request);

    expect($actor->owner->is($this->acme))->toBeTrue()
        ->and($actor->user)->toBeNull()
        ->and($actor->can('anything'))->toBeFalse(); // the fixture company has no token abilities
});
