<?php

namespace Nycorp\LiteApi;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Nycorp\LiteApi\Auth\Actor;
use Nycorp\LiteApi\Http\Controllers\ApiVersionsController;
use Nycorp\LiteApi\Middleware\ResolveApiVersion;
use Nycorp\LiteApi\Response\Envelopes\LegacyEnvelope;
use Nycorp\LiteApi\Response\Envelopes\ResponseEnvelope;
use Nycorp\LiteApi\Versioning\ApiVersion;
use Nycorp\LiteApi\Versioning\VersionRegistry;
use Nycorp\LiteApi\Webhooks\Http\WebhookEndpointController;

final class LiteApi
{
    private const ACTOR_ATTRIBUTE = 'lite-api.actor';

    private static ?Closure $actorResolver = null;

    /**
     * How a request becomes an Actor (owner, user, permissions). Default: the authenticated token owner,
     * permissions = token abilities. Return null when the request cannot act (no owner, unknown tenant...).
     *
     * LiteApi::resolveActorUsing(fn (Request $request): ?Actor => ...);
     */
    public static function resolveActorUsing(?Closure $resolver): void
    {
        self::$actorResolver = $resolver;
    }

    /**
     * Actor of the request, resolved once per request
     */
    public static function actor(?Request $request = null): ?Actor
    {
        $request ??= request();

        if (!$request->attributes->has(self::ACTOR_ATTRIBUTE)) {
            $request->attributes->set(self::ACTOR_ATTRIBUTE, self::$actorResolver ? (self::$actorResolver)($request) : self::defaultActor($request));
        }

        return $request->attributes->get(self::ACTOR_ATTRIBUTE);
    }

    private static function defaultActor(Request $request): ?Actor
    {
        $user = $request->user();

        if (!$user instanceof Model) {
            return null;
        }

        return new Actor(
            owner: $user,
            user : null,
            can  : static fn(string $permission) => method_exists($user, 'tokenCan') && $user->tokenCan($permission),
        );
    }

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
