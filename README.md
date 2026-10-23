
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
