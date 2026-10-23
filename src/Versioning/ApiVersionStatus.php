<?php

namespace Nycorp\LiteApi\Versioning;

enum ApiVersionStatus: string
{
    case ACTIVE     = 'active';
    case DEPRECATED = 'deprecated';
    case SUNSET     = 'sunset';
}
