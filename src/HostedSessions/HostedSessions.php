<?php

namespace Nycorp\LiteApi\HostedSessions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Nycorp\LiteApi\HostedSessions\Models\HostedSession;

/**
 * Hosted sessions: the API client asks for a session, gets a signed expiring URL to a page of the application,
 * hands it to its user, and is told how it ended (GET the session, or listen to HostedSessionCompleted / Failed).
 *
 * The application declares its purposes (config lite-api.hosted_sessions.purposes) and the named route of the page,
 * which takes a {token} parameter and the lite-api.hosted-session middleware.
 */
final class HostedSessions
{
    /**
     * purpose => description
     */
    public static function purposes(): array
    {
        return (array)config('lite-api.hosted_sessions.purposes', []);
    }

    public static function isPurpose(string $purpose): bool
    {
        return array_key_exists($purpose, self::purposes());
    }

    /**
     * Opens a session and returns it with its URL: the only moment the token is known.
     *
     * @return array{0: HostedSession, 1: string}
     */
    public static function start(Model $owner, string $purpose, array $context = [], ?string $returnUrl = null, ?string $locale = null, ?int $ttlMinutes = null, ?Model $actor = null): array
    {
        if (!self::isPurpose($purpose)) {
            throw new InvalidArgumentException("Unknown hosted session purpose $purpose");
        }

        $token   = HostedSession::newToken();
        $ttl     = min($ttlMinutes ?? (int)config('lite-api.hosted_sessions.ttl_minutes', 60), (int)config('lite-api.hosted_sessions.max_ttl_minutes', 1440));
        $session = new HostedSession([
            'purpose'    => $purpose,
            'context'    => $context ?: null,
            'return_url' => $returnUrl,
            'locale'     => $locale,
            'expires_at' => now()->addMinutes(max(1, $ttl)),
        ]);
        $session->token_hash = HostedSession::hash($token);
        $session->owner()->associate($owner);
        if ($actor) {
            $session->actor()->associate($actor);
        }
        $session->save();

        return [$session, self::url($token)];
    }

    public static function url(string $token): string
    {
        $route = (string)config('lite-api.hosted_sessions.route', 'lite-api.hosted-session');

        if (!Route::has($route)) {
            throw new InvalidArgumentException("Hosted session page route $route is not defined");
        }

        return route($route, ['token' => $token]);
    }
}
