<?php

namespace Nycorp\LiteApi\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Nycorp\LiteApi\Http\Service\LogService;

class ServiceLoggerHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $payload = $record->toArray();
        $payload["service"] = env("APP_NAME");
        try {
            LogService::add($payload);
        } catch (\Exception $exception) {
            Log::channel('daily')->emergency($exception->getMessage());
        }
    }

}
