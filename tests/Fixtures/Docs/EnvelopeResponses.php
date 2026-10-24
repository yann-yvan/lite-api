<?php

namespace Nycorp\LiteApi\Tests\Fixtures\Docs;

use Nycorp\LiteApi\Docs\LegacyEnvelopeResponse;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1', title: 'Fixture')]
#[OA\Schema(schema: 'Widget', properties: [new OA\Property(property: 'id', type: 'string')], type: 'object')]
#[OA\Get(path: '/single', responses: [new LegacyEnvelopeResponse(body: 'Widget')])]
#[OA\Get(path: '/list', responses: [new LegacyEnvelopeResponse(body: 'Widget', list: true)])]
#[OA\Get(path: '/page', responses: [new LegacyEnvelopeResponse(body: 'Widget', paginated: true)])]
#[OA\Get(path: '/extended', responses: [new LegacyEnvelopeResponse(body: 'Widget', with: [new OA\Property(property: 'secret', type: 'string')])])]
class EnvelopeResponses
{
}
