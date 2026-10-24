<?php

namespace Nycorp\LiteApi\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema     : 'ApiError',
    description: 'Body of every non 2xx response. Successful responses carry the endpoint payload in `data`.',
    required   : ['error'],
    properties : [
        new OA\Property(
            property  : 'error',
            required  : ['code', 'type', 'message'],
            properties: [
                new OA\Property(property: 'code', description: 'Absolute value of the response code', type: 'integer', example: 1002),
                new OA\Property(property: 'type', description: 'Stable error name, safe to branch on', type: 'string', example: 'REQUEST_VALIDATION_ERROR'),
                new OA\Property(property: 'message', type: 'string', example: 'The message.type field is required.'),
                // Untyped (any value, null included): `nullable` without `type` breaks OpenAPI 3.1 generation
                new OA\Property(property: 'details', description: 'Error context, e.g. validation errors per field, null when there is none'),
                new OA\Property(property: 'trace_id', description: 'Give it to support to find the request logs', type: 'string', nullable: true),
            ],
            type      : 'object'
        ),
    ],
    type       : 'object'
)]
class StandardEnvelopeSchemas
{
}
