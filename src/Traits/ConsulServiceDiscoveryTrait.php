<?php

namespace Nycorp\LiteApi\Traits;

use DCarbone\PHPConsulAPI\Consul;
use DCarbone\PHPConsulAPI\Catalog\CatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Nycorp\LiteApi\Exceptions\LiteResponseException;

trait ConsulServiceDiscoveryTrait
{
    use ApiResponseTrait;

    /**
     * @throws LiteResponseException|\GuzzleHttp\Exception\GuzzleException
     */
    public static function discoverServices(string $name): string
    {

        Log::channel('daily')->debug("Requesting **$name** from consul");

        try {
            # Check if services are cached
            if (Cache::has("consul_service_$name")) {
                return self::url(Cache::get("consul_service_$name"));
            }
        }catch (\Exception | \Throwable $exception) {
            Log::channel('daily')->emergency($exception->getMessage());
            throw new LiteResponseException(config('lite-api-code.request.emergency'), "Consul cache unavailable {$exception->getMessage()}");
        }

        $consul = new Consul();
        try {
            $instances = $consul->Catalog->Service($name)->Services;
        } catch (\Exception | \Throwable $exception) {
            Log::channel('daily')->emergency($exception->getMessage());
            throw new LiteResponseException(config('lite-api-code.request.emergency'), "No consul datacenter available {$exception->getMessage()}");
        }

        if (empty($instances)) {
            Log::channel('daily')->emergency("None instances of **$name** has been found ");
            throw new LiteResponseException(config('lite-api-code.request.failure'), "None instances of **$name** has been found ");
        }

        Log::channel('daily')->debug(count($instances)." instance(s) of **$name** has been found ");

        return self::url(self::getBalancedService($name, $instances));
    }

    private static function url(CatalogService $instance): string
    {
        return "{$instance->getServiceAddress()}:{$instance->getServicePort()}";
    }

    private static function getBalancedService(string $name, array $instances): CatalogService
    {
        # Simple round-robin load balancing

        # Retrieve the index of the last selected service from cache
        $cacheKey = 'service_index_' . $name;
        $index = Cache::get($cacheKey, 0);

        // Select the next service in a round-robin fashion
        $instance = array_values($instances)[$index % count($instances)];

        // Cache services for a certain duration (adjust ttl as needed)
        Cache::put("consul_service_$name", $instance, now()->addMinutes(5));

        // Increment the index for the next request
        Cache::put($cacheKey, ($index + 1) % count($instances));

        return $instance;
    }
}
