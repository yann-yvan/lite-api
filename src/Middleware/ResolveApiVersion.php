<?php

namespace Nycorp\LiteApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Events\ApiVersionUsed;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Versioning\ApiVersion;
use Nycorp\LiteApi\Versioning\VersionRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the version to the request, answers 410 once sunset,
 * and announces deprecation through the Deprecation (RFC 9745) and Sunset (RFC 8594) headers.
 */
class ResolveApiVersion
{
    public const ALIAS = 'lite-api.version';

    public function __construct(private readonly VersionRegistry $registry)
    {
    }

    public function handle(Request $request, Closure $next, string $version): Response
    {
        $apiVersion = $this->registry->get($version);
        $request->attributes->set(VersionRegistry::REQUEST_ATTRIBUTE, $apiVersion);

        $response = $apiVersion->isSunset()
            ? $this->sunset($apiVersion)
            : $next($request);

        $this->decorate($response, $apiVersion);

        event(new ApiVersionUsed($apiVersion, $request, $response->getStatusCode()));

        return $response;
    }

    private function sunset(ApiVersion $version): Response
    {
        $response = $version->envelope()->respond(ResponseCode::VERSION_SUNSET, [
            'sunset_at' => $version->sunsetAt?->toDateString(),
            'latest'    => $this->registry->latest()?->name,
        ], "API version $version->name is no longer available");

        // 410 whatever the envelope: even legacy clients must see the version is gone
        return $response->setStatusCode(Response::HTTP_GONE);
    }

    private function decorate(Response $response, ApiVersion $version): void
    {
        $response->headers->set(config('lite-api.versioning.header', 'X-Api-Version'), $version->name);

        // Sent as soon as the dates are known, so clients get the notice in advance
        if ($version->deprecatedAt !== null) {
            $response->headers->set('Deprecation', '@' . $version->deprecatedAt->getTimestamp());
        }

        if ($version->sunsetAt !== null) {
            $response->headers->set('Sunset', $version->sunsetAt->toRfc7231String());
        }

        if ($version->changelog !== null && $version->deprecatedAt !== null) {
            $response->headers->set('Link', "<$version->changelog>; rel=\"deprecation\"", false);
        }
    }
}
