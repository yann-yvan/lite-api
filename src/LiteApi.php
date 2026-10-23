<?php

namespace Nycorp\LiteApi;

use Closure;
use Illuminate\Support\Facades\Route;
use Nycorp\LiteApi\Http\Controllers\ApiVersionsController;
use Nycorp\LiteApi\Middleware\ResolveApiVersion;
use Nycorp\LiteApi\Response\Envelopes\LegacyEnvelope;
use Nycorp\LiteApi\Response\Envelopes\ResponseEnvelope;
use Nycorp\LiteApi\Versioning\ApiVersion;
use Nycorp\LiteApi\Versioning\VersionRegistry;
use Nycorp\LiteApi\Webhooks\Http\WebhookEndpointController;

final class LiteApi
{
    /**
     * Webhook endpoints management for the authenticated owner, to call inside an authenticated (versioned) group.
     * Add LiteApi::webhookDocs() to the version documentation annotations.
     */
    public static function webhookRoutes(string $prefix = 'webhooks'): void
    {
        Route::prefix($prefix)->controller(WebhookEndpointController::class)->group(function () {
            Route::get('events', 'events');
            Route::get('', 'index');
            Route::post('', 'store');
            Route::get('{endpoint}', 'show')->whereUuid('endpoint');
            Route::put('{endpoint}', 'update')->whereUuid('endpoint');
            Route::delete('{endpoint}', 'destroy')->whereUuid('endpoint');
            Route::post('{endpoint}/rotate-secret', 'rotateSecret')->whereUuid('endpoint');
            Route::post('{endpoint}/test', 'test')->whereUuid('endpoint');
            Route::get('{endpoint}/deliveries', 'deliveries')->whereUuid('endpoint');
            Route::post('{endpoint}/deliveries/{delivery}/retry', 'retry')->whereUuid(['endpoint', 'delivery']);
        });
    }

    /**
     * OpenAPI annotations of the webhook routes and schemas
     */
    public static function webhookDocs(): string
    {
        return __DIR__ . '/Webhooks/Http';
    }

    /**
     * Register the routes of a declared version under its prefix.
     *
     * LiteApi::version('v2.0', function () {
     *     MessageController::expose('messages');
     * });
     *
     * Pass $prefix: '' to tag existing unversioned routes with a version without moving them.
     */
    public static function version(string $version, Closure|string $routes, ?string $prefix = null): void
    {
        // Fail at boot, not at the first request, when the version is not declared
        app(VersionRegistry::class)->get($version);

        Route::prefix($prefix ?? $version)
            ->middleware(ResolveApiVersion::class . ':' . $version)
            ->group($routes);
    }

    /**
     * Public listing of the declared versions and their lifecycle
     */
    public static function versionsRoute(string $uri = 'versions'): void
    {
        Route::get($uri, ApiVersionsController::class);
    }

    public static function currentVersion(): ?ApiVersion
    {
        return app(VersionRegistry::class)->current();
    }

    public static function envelope(): ResponseEnvelope
    {
        return app(self::currentVersion()?->envelope ?? config('lite-api.versioning.default_envelope', LegacyEnvelope::class));
    }
}
