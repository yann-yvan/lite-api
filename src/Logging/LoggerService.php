<?php

namespace Nycorp\LiteApi\Logging;

use Monolog\Logger;

class LoggerService
{
    /**
     * Create a custom Monolog instance.
     */
    public function __invoke(array $config): Logger
    {
        return new Logger(
            env('APP_NAME'),
            [
                new ServiceLoggerHandler(),
            ]
        );
    }
}
