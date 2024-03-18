<?php

namespace Nycorp\LiteApi\Traits;

use DCarbone\PHPConsulAPI\Consul;
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
        $consul = new Consul();
        try {
            $instances = $consul->Catalog->Service($name)->Services;
        } catch (\Exception $exception) {
            Log::emergency($exception->getMessage());
            throw new LiteResponseException(config('lite-api-code.request.emergency'), "No consul datacenter available {$exception->getMessage()}");
        }


        if (empty($instances)) {
            Log::emergency("None instances of **$name** has been found ");
            throw new LiteResponseException(config('lite-api-code.request.failure'), "None instances of **$name** has been found ");
        }

        # Simple round-robin load balancing

        # Retrieve the index of the last selected service from cache
        $cacheKey = 'service_index_' . $name;
        $index = Cache::get($cacheKey, 0);

        // Select the next service in a round-robin fashion
        $instance= array_values($instances)[$index % count($instances)];

        // Increment the index for the next request
        Cache::put($cacheKey, ($index + 1) % count($instances));


        return "{$instance->getServiceAddress()}:{$instance->getServicePort()}";
    }
}
