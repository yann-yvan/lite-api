<?php

namespace Nycorp\LiteApi\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema     : 'LiteApiLegacyResponse',
    description: 'Every response is HTTP 200. The outcome is in `success` and `code` (absolute value of the response code).',
    properties : [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 1000),
        new OA\Property(property: 'message', type: 'string', example: 'Request success.'),
        new OA\Property(property: 'body', description: 'Endpoint payload', nullable: true),
        new OA\Property(property: 'token', description: 'Present only on authentication routes', type: 'string', nullable: true),
        new OA\Property(property: 'trace_id', description: 'Present only when success is false', type: 'string', nullable: true),
    ],
    type       : 'object'
)]
class LegacyEnvelopeSchemas
{
}
