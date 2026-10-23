
## Features

1. Log into database and into micro-service
2. Swagger documentation using **[darkaonline/l5-swagger](https://github.com/DarkaOnLine/L5-Swagger)**
3. Service discovery using **[dcarbone/php-consul-api](https://github.com/dcarbone/php-consul-api)**
4. Jwt auth using **[tymon/jwt-auth](https://github.com/tymon/jwt-auth)**
5. SQL to migration using **[bennett-treptow/laravel-migration-generator](https://github.com/bennett-treptow/laravel-migration-generator)**
6. SQL to model using **[reliese/laravel](https://github.com/reliese/laravel)**
7. API versioning (Meta Graph API style): frozen versions, deprecation and sunset headers, one response envelope and one OpenAPI documentation per version, contract tests


#### Installation (with Composer)

```composer
composer require nycorp/lite-api
```

## Usage

```shell
php artisan vendor:publish --provider="Nycorp\LiteApi\Providers\LiteApiServiceProvider"
```

### Logging :

#### Enable logger to call your remote logger service or log in app database

the default value is **false**
```dotenv
LOG_REMOTE=false
```

To enable log service in .env
```dotenv
LOG_CHANNEL=stack
LOG_STACK=daily,service_log
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```
use the logger provide by Laravel as usual

```php
Log::info("Delete: started {$this->modelLogger($id)} ");
```

## API versioning

A version (`v1.0`, `v2.0`...) is a **frozen contract**: request formats, response formats, behaviour and webhook payloads.
Old versions keep working untouched while new ones evolve.

| Allowed in a released version                     | Needs a new version                                      |
|---------------------------------------------------|----------------------------------------------------------|
| New endpoint, new optional input field            | Removing or renaming a field, changing its type/format   |
| New field in a response, new webhook event type   | Making a field required, changing an error code/default |

### 1. Declare the versions

```shell
php artisan vendor:publish --tag=lite-api-versioning
```

```php
// config/lite-api.php
'versions' => [
    'v1.0' => [
        'envelope'      => LegacyEnvelope::class,   // historical format, always HTTP 200
        'released_at'   => '2025-01-01',
        'deprecated_at' => '2027-01-01',            // announced to clients from now on
        'sunset_at'     => '2027-07-01',            // HTTP 410 from this date
        'changelog'     => 'https://example.com/docs/changelog#v1.0',
    ],
    'v2.0' => [
        'envelope'    => StandardEnvelope::class,   // real HTTP statuses
        'released_at' => '2026-11-01',
    ],
],
```

The status is computed from the dates: `active`, `deprecated` once `deprecated_at` is reached, `sunset` once `sunset_at` is reached.

### 2. Register the routes

```php
use Nycorp\LiteApi\LiteApi;

// /api/v2.0/messages/...
LiteApi::version('v2.0', function () {
    MessageController::expose('messages');
});

// Existing unversioned routes: tag them as v1.0 without moving them
LiteApi::version('v1.0', base_path('routes/api.php'), prefix: '');

// GET /api/versions : declared versions and their lifecycle
LiteApi::versionsRoute();
```

Every versioned response carries `X-Api-Version` (configurable). Once `deprecated_at` / `sunset_at` are set,
it also carries `Deprecation` (RFC 9745), `Sunset` (RFC 8594) and `Link: <changelog>; rel="deprecation"`.
A sunset version answers `410` with the code `VERSION_SUNSET` (`1200`) and the latest version in the body.

### 3. Response envelopes

`liteResponse()` delegates to the envelope of the version serving the request: controllers do not change.
Outside versioned routes (unversioned routes, jobs, console) `versioning.default_envelope` is used (legacy).

**LegacyEnvelope**: unchanged historical format.

```json
{"success": false, "message": "...", "code": 1002, "body": {...}, "trace_id": "..."}
```

**StandardEnvelope**: HTTP status from the response code (`422` validation, `404` not found, `401` token, `409` duplicate...,
overridable with `lite-api.http_status`).

```json
{"data": {...}}
{"error": {"code": 1002, "type": "REQUEST_VALIDATION_ERROR", "message": "...", "details": {...}, "trace_id": "..."}}
```

The envelope also applies to responses built before the version middleware (auth middlewares...), the version is read from the route.

Custom envelope: implement `Nycorp\LiteApi\Response\Envelopes\ResponseEnvelope`.

### 4. Track usage before sunsetting

`Nycorp\LiteApi\Events\ApiVersionUsed` (version, request, HTTP status) is fired on every versioned request.
Listen to it to know which clients still call a version before deprecating it; storage is up to the application.

### 5. One OpenAPI documentation per version

Give a version its own annotation folder; an l5-swagger documentation is registered for it automatically
(`api-docs-v2.0.json`, UI on `api/docs/v2.0`), with the envelope schemas (`LiteApiLegacyResponse`, `LiteApiError`).

```php
'v2.0' => [
    'envelope' => StandardEnvelope::class,
    'docs'     => [
        'annotations' => [app_path('Http/Docs/V2')],
        'route'       => 'api/docs/v2.0',   // optional
        'title'       => 'My API',          // optional, version appended
        'base'        => env('APP_URL') . '/api/v2.0', // optional, replaces l5-swagger paths.base for this version
    ],
],
```

- Each version folder holds its own `#[OA\Info(version: '2.0')]` and `#[OA\Server(url: '.../api/v2.0')]`.
- Annotations common to all versions go in `versioning.docs.shared_annotations`.
- The folders of the other versions are excluded automatically. If an existing documentation scans the whole
  `app` folder, add the version folders to its `paths.excludes`.
- Documentations declared by hand in `config/l5-swagger.php` with the same name win.

```shell
php artisan l5-swagger:generate v2.0
```

### 6. Contract tests

Freeze the shape of each endpoint once a version is released:

```php
use Nycorp\LiteApi\Testing\AssertsApiContract;

class AgentApiTest extends TestCase
{
    use AssertsApiContract;

    public function test_search_contract(): void
    {
        $this->assertApiContract($this->getJson('/api/v2.0/agent/search'), 'v2.0/agent.search');
    }
}
```

The first run records `tests/Contracts/v2.0/agent.search.json` (commit it). Later runs fail when a field is removed,
changes type or becomes null; new fields pass. Re-record with `LITE_API_UPDATE_CONTRACTS=1` only when opening a new version.

## Outbound webhooks

Opt-in: nothing is migrated until you publish the migrations.

```shell
php artisan vendor:publish --tag=lite-api-webhooks
php artisan migrate
```

Endpoints belong to an owner (company, account...): add `HasWebhookEndpoints` to that model.

### 1. Declare and dispatch events

```php
// config/lite-api.php
'webhooks' => [
    'header_prefix' => 'X-MyApp',            // X-MyApp-Event, -Delivery, -Timestamp, -Signature, -Api-Version
    'events'        => [
        'message.received' => 'A contact sent a message',
    ],
],
```

```php
class MessageReceived extends WebhookEvent
{
    public function __construct(private Message $message) {}

    public function type(): string { return 'message.received'; }
    public function owner(): Model { return $this->message->company; }

    // Endpoints can restrict themselves to some values through their `filters`
    public function context(): array { return ['app' => $this->message->app->hashid]; }

    // Payload per API version: endpoints keep the version they were created with
    public function data(?string $apiVersion): array { return MessagePresenter::present($this->message); }
}

Webhooks::dispatch(new MessageReceived($message)); // waits for the DB commit
```

Each subscribed endpoint gets a delivery, queued on the `webhooks` queue (run a worker for it).

### 2. Delivery

- Body: `{"id": "evt_…", "type": "...", "api_version": "v2.0", "created_at": "...", "data": {...}}`
- Signature: `sha256=hex(hmac_sha256(secret, "{timestamp}.{raw body}"))`; receivers can use `WebhookSigner::verify()`
- Success: 2xx within `timeout` seconds, redirects are not followed
- Retries: `backoff` (10s, 1min, 5min, 30min, 2h, 6h, 12h), then `WebhookDeliveryFailed`
- An endpoint failing for `disable_after_hours` (72h) is disabled: `WebhookEndpointDisabled` (notify the owner)
- SSRF: URLs resolving to private/reserved addresses are refused on save and before each delivery
  (`allow_private_urls` for local development only)
- History: `webhook_deliveries`, pruned after `retention_days` (schedule `model:prune`)

### 3. Management API

Inside an authenticated (versioned) group; the owner is the authenticated user by default
(`WebhookEndpointController::resolveOwnerUsing()` to change it).

```php
LiteApi::version('v2.0', function () {
    LiteApi::webhookRoutes();   // /webhooks...
});
```

| Route | |
|---|---|
| `GET webhooks/events` | Event catalog |
| `GET/POST webhooks`, `GET/PUT/DELETE webhooks/{id}` | Endpoints (secret returned once, on creation) |
| `POST webhooks/{id}/rotate-secret` | New secret |
| `POST webhooks/{id}/test` | Sends `webhook.test` now |
| `GET webhooks/{id}/deliveries` | History (`?status=pending\|succeeded\|failed`) |
| `POST webhooks/{id}/deliveries/{delivery}/retry` | Redelivery, same event id |

Add `LiteApi::webhookDocs()` to the version `docs.annotations` to document these routes.

## REST resources with CoreController

One controller per resource serves every client (integrators, mobile app, web app). Everything below is opt-in:
`expose()` and `CoreController` behave as before by default.

```php
class AgentController extends CoreController
{
    // Strict creation for REST resources (firstOrCreate would return an existing record on POST)
    protected string $createStrategy = self::CREATE_STRATEGY_CREATE_ONLY;

    // Every action starts from this query: scope it to the actor
    protected function baseQuery(): Builder
    {
        return Agent::query()->whereBelongsTo(LiteApi::actor()->owner);
    }

    // Public ids instead of the primary key
    protected function findRecord(Builder $query, mixed $id): ?Model
    {
        return $query->whereKey(Agent::decodeHashid($id))->first();
    }

    // Stable representation of the version (also usable for webhooks)
    protected function present(Model $model): mixed
    {
        return AgentPresenter::present($model);
    }

    // Business actions; throw LiteResponseException to refuse with a message
    public function create(array $data): Model { return CreateAgent::run(LiteApi::actor()->owner, $data); }
    protected function persistUpdate(Model $model, array $data): Model { return UpdateAgent::run($model, $data); }
    protected function persistDelete(Model $model): void { DeleteAgent::run($model); }
}

// GET /agents, GET /agents/{id}, POST /agents, PUT|PATCH /agents/{id}, DELETE /agents/{id}, PATCH /agents/{id}/restore
AgentController::expose('agents', softDelete: true, rest: true);
```

Other hooks: `dataForAdd()` / `dataForUpdate()` (input kept, fillable by default), `onBeforeRestore()`.

### Actor and permissions

The actor is who calls the API: the owner whose data is reached, the user acting if any, and their permissions.
Default: the authenticated token owner, permissions = token abilities. Resolve it your way once:

```php
LiteApi::resolveActorUsing(function (Request $request): ?Actor {
    // company API key, or user token + X-Company header...
    return new Actor(owner: $company, user: $user, can: fn (string $permission) => ...);
});

Route::middleware(RequirePermission::class . ':permissions.manage_integrations')->group(...);
```

Webhook endpoints use the actor owner by default.
