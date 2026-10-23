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

final class LiteApi
{
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
