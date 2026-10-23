<?php

namespace Nycorp\LiteApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use Nycorp\LiteApi\Versioning\ApiVersion;
use Nycorp\LiteApi\Versioning\VersionRegistry;

class ApiVersionsController
{
    use ApiResponseTrait;

    public function __invoke(VersionRegistry $registry): JsonResponse
    {
        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, [
            'latest'   => $registry->latest()?->name,
            'versions' => array_values(array_map(static fn(ApiVersion $version) => $version->toArray(), $registry->all())),
        ]);
    }
}
