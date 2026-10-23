<?php

namespace Nycorp\LiteApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Versioning\ApiVersion;

/**
 * Fired on every versioned request. Listen to it to track who still calls a version
 * before deprecating or sunsetting it (storage is up to the application).
 */
class ApiVersionUsed
{
    use Dispatchable;

    public function __construct(
        public readonly ApiVersion $version,
        public readonly Request    $request,
        public readonly int        $status,
    )
    {
    }
}
