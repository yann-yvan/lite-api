<?php

namespace Nycorp\LiteApi\Docs;

use OpenApi\Attributes as OA;

#[OA\Schema(
    // No description here: each response already carries it (LegacyEnvelopeResponse), doc renderers would show it twice
    schema     : 'ApiEnvelope',
    properties : [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 1000),
        new OA\Property(property: 'message', type: 'string', example: 'Request success.'),
        // Untyped (any value, null included): `nullable` without `type` breaks OpenAPI 3.1 generation
        new OA\Property(property: 'body', description: 'Endpoint payload, null when there is none'),
        new OA\Property(property: 'token', description: 'Present only on authentication routes', type: 'string', nullable: true),
        new OA\Property(property: 'trace_id', description: 'Present only when success is false', type: 'string', nullable: true),
    ],
    type       : 'object'
)]
#[OA\Schema(
    schema     : 'ApiPage',
    description: 'A page of results: `data` holds the items. Ask another page with `?page=`',
    properties : [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
        new OA\Property(property: 'per_page', type: 'integer', example: 50),
        new OA\Property(property: 'total', type: 'integer', example: 120),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'from', type: 'integer', nullable: true),
        new OA\Property(property: 'to', type: 'integer', nullable: true),
        new OA\Property(property: 'first_page_url', type: 'string'),
        new OA\Property(property: 'last_page_url', type: 'string'),
        new OA\Property(property: 'next_page_url', type: 'string', nullable: true),
        new OA\Property(property: 'prev_page_url', type: 'string', nullable: true),
        new OA\Property(property: 'path', type: 'string'),
    ],
    type       : 'object'
)]
class LegacyEnvelopeSchemas
{
}
