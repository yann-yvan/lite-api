<?php

namespace Nycorp\LiteApi\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return self::liteResponse(ResponseCode::TOKEN_EXPIRED);
        } catch (TokenBlacklistedException $e) {
            return self::liteResponse(ResponseCode::TOKEN_BLACK_LISTED);
        } catch (TokenInvalidException $e) {
            return self::liteResponse(ResponseCode::TOKEN_INVALID);
        } catch (Exception|\Throwable $e) {
            return self::liteResponse(ResponseCode::TOKEN_NOT_FOUND);
        }

        return $next($request);
    }
}
