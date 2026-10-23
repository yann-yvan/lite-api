<?php

namespace Nycorp\LiteApi\Traits;

use Exception;
use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\LiteApi;

trait ApiResponseTrait
{
    /**
     * parsing api response according the specification of the current API version
     *
     * @throws Exception
     */
    public static function liteResponse(int $code, mixed $data = null, ?string $message = null, ?string $token = null): JsonResponse
    {
        return LiteApi::envelope()->respond($code, $data, $message, $token);
    }
}
