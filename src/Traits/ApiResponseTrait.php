<?php

namespace Nycorp\LiteApi\Traits;

use Exception;
use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Response\Builder;

trait ApiResponseTrait
{
    /**
     * parsing api response according the specification
     *
     * @param int $code
     * @param null $data
     * @param string|null $message
     * @param string|null $token
     *
     * @return JsonResponse
     * @throws Exception
     */
    public static function liteResponse(int $code, mixed $data = null, string|null $message = null, string|null $token = null): JsonResponse
    {
        $builder = new Builder($code, $message);
        $builder->setData($data);
        $builder->setToken($token);
        return response()->json($builder->reply(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

}
