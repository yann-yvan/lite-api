<?php

namespace Nycorp\LiteApi\Webhooks;

enum AttemptResult
{
    case SUCCEEDED;

    /** Transient failure (timeout, non 2xx...): try again later */
    case RETRY;

    /** Will never succeed as is (endpoint disabled, URL refused): stop */
    case ABANDONED;
}
