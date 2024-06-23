<?php

namespace Nycorp\LiteApi\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Nycorp\LiteApi\Http\Controllers\Core\LogLocalController;
use Nycorp\LiteApi\Http\Service\LogRemoteService;
use Ramsey\Uuid\Uuid;

class ServiceLoggerHandler extends AbstractProcessingHandler
{
    private string $actionId;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->actionId = Uuid::uuid4()->toString();
    }

    protected function write(LogRecord $record): void
    {
        $payload = $record->toArray();
        $payload["service"] = env("APP_NAME");
        $payload["extra"] = array_merge($payload["extra"] ?? [], ['action_id' => $this->actionId]);
        try {
            if (env("LOG_REMOTE", false)) {
                LogRemoteService::add($payload);
            } else {
                (new LogLocalController())->add(request()->merge($payload));
            }

        } catch (\Exception $exception) {
            Log::channel('daily')->emergency($exception->getMessage());
        }
    }

}
