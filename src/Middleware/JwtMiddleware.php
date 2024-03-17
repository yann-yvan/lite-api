<?php

namespace Nycorp\LiteApi\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
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
        } catch (TokenInvalidException $e) {
            return self::liteResponse(config('lite-api-code.token.invalid'));
        } catch (TokenExpiredException $e) {
            return self::liteResponse(config('lite-api-code.token.expired'));
        } catch (TokenBlacklistedException $e) {
            return self::liteResponse(config('lite-api-code.token.black_listed'));
        } catch (Exception|\Throwable $e) {
            return self::liteResponse(config('lite-api-code.token.not_found'));
        }

        return $next($request);
    }
}
