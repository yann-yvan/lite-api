<?php

namespace Nycorp\LiteApi\Http\Service;


use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Nycorp\LiteApi\Response\DefResponse;
use Nycorp\LiteApi\Traits\ConsulServiceDiscoveryTrait;
use Nycorp\LiteApi\Traits\RequestServiceTrait;

class BaseService
{
    use RequestServiceTrait, ConsulServiceDiscoveryTrait;

    /**
     * The Singleton's instance is stored in a static field. This field is an
     * array, because we'll allow our Singleton to have subclasses. Each item in
     * this array will be an instance of a specific Singleton's subclass. You'll
     * see how this works in a moment.
     */
    private static array $instances = [];

    protected string $name = "log";
    protected string $actionPrefix = "log";

    protected DefResponse $response;

    /**
     * @throws GuzzleException
     */
    public static function add(array $payload = [], array $headers = []): static
    {
        return self::request(payload: $payload, service: "add", headers: $headers);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected static function request(array $payload = [], string $baseUrl = null, string $service = null, string $path = null, string $method = "post", array $headers = [], bool $async = false): static
    {
        $instance = self::instance();
        $response = $instance->consume(
            payload: $payload,
            baseUrl: $baseUrl ?? self::discoverServices($instance->name),
            endpoint: ($path ?? $instance->actionPrefix) . "/$service",
            async: $async,
            method: $method,
            headers: $headers
        );

        $instance->response = new DefResponse($response);
        return $instance;
    }

    private static function instance(): static
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    /**
     * @throws GuzzleException
     */
    public static function update(mixed $id, array $payload = [], array $headers = []): static
    {
        return self::request(payload: $payload, service: "update/$id", method: "put", headers: $headers);
    }

    /**
     * @throws GuzzleException
     */
    public static function search(array $payload = [], mixed $id = null, array $headers = []): static
    {
        return self::request(payload: $payload, service: "search{${$id?"/$id":''}}", method: "get", headers: $headers);
    }

    /**
     * @throws GuzzleException
     */
    public static function delete(mixed $id, array $payload = [], array $headers = []): static
    {
        return self::request(payload: $payload, service: "delete/$id", method: "delete", headers: $headers);
    }

    /**
     * @throws GuzzleException
     */
    public static function restore(mixed $id, array $payload = [], array $headers = []): static
    {
        return self::request(payload: $payload, service: "restore/$id", method: "patch", headers: $headers);
    }

    /**
     * @return DefResponse
     */
    public function getResponse(): DefResponse
    {
        return $this->response;
    }
}
