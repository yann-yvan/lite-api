<?php

namespace Nycorp\LiteApi\Models;

class ResponseCode
{
    #Token
    public const TOKEN_EXPIRED = -1;
    public const TOKEN_BLACK_LISTED = -2;
    public const TOKEN_INVALID = -3;
    public const TOKEN_NOT_FOUND = -4;
    public const TOKEN_USER_NOT_FOUND = -5;

    #Request
    public const REQUEST_SUCCESS = 1000;
    public const REQUEST_FAILURE = -1001;
    public const REQUEST_VALIDATION_ERROR = -1002;
    public const REQUEST_EXPIRED = -1003;
    public const REQUEST_TRYING_TO_INSERT_DUPLICATE = -1004;
    public const REQUEST_NOT_AUTHORIZED = -1005;
    public const REQUEST_EXCEPTION = -1006;
    public const REQUEST_NOT_FOUND = -1007;
    public const REQUEST_WRONG_JSON_FORMAT = -1008;
    public const REQUEST_SERVICE_NOT_AVAILABLE = -1009;
    public const REQUEST_EMERGENCY = -1010;

    #Auth
    public const AUTH_ACCOUNT_NOT_VERIFY = -1100;
    public const AUTH_WRONG_USERNAME = -1101;
    public const AUTH_WRONG_PASSWORD = -1102;
    public const AUTH_WRONG_CREDENTIALS = -1103;
    public const AUTH_ACCOUNT_VERIFIED = 1104;
    public const AUTH_NOT_EXISTS = -1105;


}


