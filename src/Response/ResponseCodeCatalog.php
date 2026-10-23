<?php

namespace Nycorp\LiteApi\Response;

class ResponseCodeCatalog
{
    /**
     * Stable name of a code, built from config lite-api-code: request.not_found => REQUEST_NOT_FOUND
     */
    public static function nameOf(int $code): ?string
    {
        foreach (config('lite-api-code', []) as $group => $codes) {
            foreach ($codes as $key => $value) {
                if ($value === $code) {
                    return strtoupper("{$group}_$key");
                }
            }
        }

        return null;
    }
}
