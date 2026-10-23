<?php

namespace Nycorp\LiteApi\Versioning;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use InvalidArgumentException;
use Nycorp\LiteApi\Middleware\ResolveApiVersion;

final class VersionRegistry
{
    public const REQUEST_ATTRIBUTE = 'lite-api.version';

    /** @var array<string, ApiVersion> */
    private array $versions = [];

    public function __construct(array $versions = [])
    {
        foreach ($versions as $name => $config) {
            $this->versions[$name] = ApiVersion::fromConfig($name, $config ?? []);
        }
    }

    public static function fromConfig(): self
    {
        return new self(config('lite-api.versions', []));
    }

    /**
     * @return array<string, ApiVersion>
     */
    public function all(): array
    {
        return $this->versions;
    }

    public function find(string $name): ?ApiVersion
    {
        return $this->versions[$name] ?? null;
    }

    public function get(string $name): ApiVersion
    {
        return $this->find($name) ?? throw new InvalidArgumentException("API version [$name] is not declared in config lite-api.versions");
    }

    /**
     * Most recent version still served, in declaration order
     */
    public function latest(): ?ApiVersion
    {
        $served = array_filter($this->versions, static fn(ApiVersion $version) => !$version->isSunset());

        return $served === [] ? null : end($served);
    }

    /**
     * Version serving the request. Falls back on the route middleware so that responses built
     * before ResolveApiVersion runs (auth failures...) still use the version envelope.
     */
    public function current(?Request $request = null): ?ApiVersion
    {
        $request ??= app()->bound('request') ? app('request') : null;

        if ($request === null) {
            return null;
        }

        $version = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if ($version instanceof ApiVersion) {
            return $version;
        }

        $route = $request->route();

        if (!$route instanceof Route) {
            return null;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            foreach ([ResolveApiVersion::class, ResolveApiVersion::ALIAS] as $name) {
                if (str_starts_with($middleware, "$name:")) {
                    return $this->find(substr($middleware, strlen($name) + 1));
                }
            }
        }

        return null;
    }
}
