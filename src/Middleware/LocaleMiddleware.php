<?php

namespace Nycorp\LiteApi\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class LocaleMiddleware
{
    public function handle($request, Closure $next, $guard = null)
    {
        /**
         * requests hasHeader is used to check the Accept-Language header from the REST API's
         */
        if ($request->hasHeader('Accept-Language')) {
            /**
             * If Accept-Language header found then set it to the default locale
             */
            $lang = explode('-', $request->header('Accept-Language'));
            $lang = Str::lower(end($lang));
            App::setLocale($lang);
        }

        return $next($request);
    }
}
