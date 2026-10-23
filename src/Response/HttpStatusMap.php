<?php

namespace Nycorp\LiteApi\Response;

use Nycorp\LiteApi\Models\ResponseCode;

/**
 * Response code => HTTP status, overridable through config lite-api.http_status.
 * Unknown codes: 200 when positive (success), 400 otherwise.
 */
class HttpStatusMap
{
    private const DEFAULTS = [
        ResponseCode::TOKEN_EXPIRED                      => 401,
        ResponseCode::TOKEN_BLACK_LISTED                 => 401,
        ResponseCode::TOKEN_INVALID                      => 401,
        ResponseCode::TOKEN_NOT_FOUND                    => 401,
        ResponseCode::TOKEN_USER_NOT_FOUND               => 401,

        ResponseCode::REQUEST_SUCCESS                    => 200,
        ResponseCode::REQUEST_FAILURE                    => 400,
        ResponseCode::REQUEST_VALIDATION_ERROR           => 422,
        ResponseCode::REQUEST_EXPIRED                    => 410,
        ResponseCode::REQUEST_TRYING_TO_INSERT_DUPLICATE => 409,
        ResponseCode::REQUEST_NOT_AUTHORIZED             => 403,
        ResponseCode::REQUEST_EXCEPTION                  => 500,
        ResponseCode::REQUEST_NOT_FOUND                  => 404,
        ResponseCode::REQUEST_WRONG_JSON_FORMAT          => 400,
        ResponseCode::REQUEST_SERVICE_NOT_AVAILABLE      => 503,
        ResponseCode::REQUEST_EMERGENCY                  => 500,

        ResponseCode::AUTH_ACCOUNT_NOT_VERIFY            => 403,
        ResponseCode::AUTH_WRONG_USERNAME                => 401,
        ResponseCode::AUTH_WRONG_PASSWORD                => 401,
        ResponseCode::AUTH_WRONG_CREDENTIALS             => 401,
        ResponseCode::AUTH_ACCOUNT_VERIFIED              => 200,
        ResponseCode::AUTH_NOT_EXISTS                    => 404,

        ResponseCode::VERSION_SUNSET                     => 410,
    ];

    public function for(int $code): int
    {
        return config('lite-api.http_status', [])[$code]
            ?? self::DEFAULTS[$code]
            ?? ($code > 0 ? 200 : 400);
    }
}
