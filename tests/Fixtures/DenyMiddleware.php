<?php

namespace Nycorp\LiteApi\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Models\ResponseCode;

/**
 * Stands for an auth middleware running before ResolveApiVersion
 */
class DenyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return Responder::liteResponse(ResponseCode::TOKEN_NOT_FOUND);
    }
}
