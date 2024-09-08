<?php

namespace Nycorp\LiteApi\Models;

class ResponseCode
{
    public static function token() {
        return new class {
            public const EXPIRED = -1;
            public const BLACK_LISTED = -2;
            public const INVALID = -3;
            public const NOT_FOUND = -4;
            public const USER_NOT_FOUND = -5;
        };
    }

    public static function request() {
        return new class {
            public const SUCCESS = 1000;
            public const FAILURE = -1001;
            public const VALIDATION_ERROR = -1002;
            public const EXPIRED = -1003;
            public const TRYING_TO_INSERT_DUPLICATE = -1004;
            public const NOT_AUTHORIZED = -1005;
            public const EXCEPTION = -1006;
            public const NOT_FOUND = -1007;
            public const WRONG_JSON_FORMAT = -1008;
            public const SERVICE_NOT_AVAILABLE = -1009;
            public const EMERGENCY = -1010;
        };
    }

    public static function auth() {
        return new class {
            public const ACCOUNT_NOT_VERIFY = -1100;
            public const WRONG_USERNAME = -1101;
            public const WRONG_PASSWORD = -1102;
            public const WRONG_CREDENTIALS = -1103;
            public const ACCOUNT_VERIFIED = 1104;
            public const NOT_EXISTS = -1105;
        };
    }
}


