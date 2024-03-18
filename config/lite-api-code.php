<?php
/**
 * Created by PhpStorm.
 * User: yann-yvan
 * Date: 08/09/18
 * Time: 13:58
 */
return [

    /*
     * Token message code
     */
    'token' => [
        'expired' => -1,
        'black_listed' => -2,
        'invalid' => -3,
        'not_found' => -4,
        'user_not_found' => -5,
    ],

    /*
    * Common request message code
    */
    'request' => [
        'success' => 1000,
        'failure' => -1001,
        'validation_error' => -1002,
        'expired' => -1003,
        'trying_to_insert_duplicate' => -1004,
        'not_authorized' => -1005,
        'exception' => -1006,
        'not_found' => -1007,
        'wrong_json_format' => -1008,
        'service_not_available' => -1009,
        'emergency' => -1010,
    ],

    /*
     * Authentication message code
     */
    'auth' => [
        'account_not_verify' => -1100,
        'wrong_username' => -1101,
        'wrong_password' => -1102,
        'wrong_credentials' => -1103,
        'account_verified' => 1104,
        'not_exists' => -1105,
    ],
];
