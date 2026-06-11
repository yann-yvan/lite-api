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
        $handler = new ServiceLoggerHandler();

        app()->instance(ServiceLoggerHandler::class, $handler);

        return new Logger(
            config('app.name'),
            [$handler]
        );
    }
}
