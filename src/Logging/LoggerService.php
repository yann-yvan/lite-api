<?php

namespace Nycorp\LiteApi\Logging;

use Monolog\Logger;

class LoggerService
{
    private static ServiceLoggerHandler $logger;

    /**
     * @return ServiceLoggerHandler
     */
    public static function getLogger()
    {
        return self::$logger;
    }

    /**
     * Create a custom Monolog instance.
     */
    public function __invoke(array $config): Logger
    {
        self::$logger = new ServiceLoggerHandler();
        return new Logger(
            env('APP_NAME'),
            [
                self::$logger,
            ]
        );
    }
}
