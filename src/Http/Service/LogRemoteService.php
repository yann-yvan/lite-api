<?php

namespace Nycorp\LiteApi\Http\Service;

class LogRemoteService extends BaseService
{
    public static function add(array $payload = [], array $headers = []): static
    {
        return self::request(payload: $payload, service: "add", headers: $headers, async: false);
    }
}
