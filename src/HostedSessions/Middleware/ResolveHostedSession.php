<?php

namespace Nycorp\LiteApi\HostedSessions\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nycorp\LiteApi\HostedSessions\Models\HostedSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {token} of a hosted page into its session (request attribute "hosted_session").
 * A finished or expired session is still resolved, so the page can say so; use requireUsable() to refuse it.
 *
 * Route::get('connect/{token}', ...)->middleware('lite-api.hosted-session:whatsapp')
 */
class ResolveHostedSession
{
    public const ALIAS     = 'lite-api.hosted-session';
    public const ATTRIBUTE = 'hosted_session';

    public function handle(Request $request, Closure $next, ?string $purpose = null): Response
    {
        $session = HostedSession::findByToken((string)$request->route('token'));

        if (!$session || ($purpose !== null && !in_array($session->purpose, explode('|', $purpose), true))) {
            abort(404);
        }

        // Tokens are secrets: keep the page out of caches, referrers and search engines
        $request->attributes->set(self::ATTRIBUTE, $session);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    public static function session(Request $request): ?HostedSession
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }
}
