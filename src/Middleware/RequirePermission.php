<?php

namespace Nycorp\LiteApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * The actor must hold the permission: RequirePermission::class . ':permissions.manage_integrations'
 */
class RequirePermission
{
    use ApiResponseTrait;

    public const ALIAS = 'lite-api.permission';

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $actor = LiteApi::actor($request);

        if ($actor === null) {
            return self::liteResponse(ResponseCode::TOKEN_NOT_FOUND);
        }

        if (!$actor->can($permission)) {
            return self::liteResponse(ResponseCode::REQUEST_NOT_AUTHORIZED, message: "Missing the {$permission} permission");
        }

        return $next($request);
    }
}
