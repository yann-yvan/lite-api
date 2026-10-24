<?php

namespace Nycorp\LiteApi\HostedSessions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Nycorp\LiteApi\HostedSessions\Models\HostedSession;

/**
 * A hosted session reached its end: listen to it to notify the API client (webhook...).
 */
class HostedSessionCompleted
{
    use Dispatchable;

    public function __construct(public readonly HostedSession $session)
    {
    }
}
