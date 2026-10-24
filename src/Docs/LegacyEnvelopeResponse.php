<?php

namespace Nycorp\LiteApi\Docs;

use OpenApi\Attributes as OA;

/**
 * Documented response in the legacy envelope (always HTTP 200: success, code, message, body), with a typed body.
 * Generated documentation (Swagger UI, Mintlify...) then shows the real shape instead of a free text.
 *
 *     responses: [new LegacyEnvelopeResponse(body: 'Agent', paginated: true)]
 *     responses: [new LegacyEnvelopeResponse(body: 'Channel', with: [new OA\Property(property: 'secret', type: 'string')])]
 *     responses: [new LegacyEnvelopeResponse(schema: new OA\Schema(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')))]
 *
 * @param string|null    $body      component schema of the body ('Agent' => #/components/schemas/Agent)
 * @param bool           $list      body is an array of $body
 * @param bool           $paginated body is a page (LiteApiPage) whose data is an array of $body
 * @param OA\Property[]  $with      extra properties of the body (a secret shown once...), or the whole body when $body is null
 * @param OA\Schema|null $schema    the body schema itself, for shapes the other parameters cannot express
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class LegacyEnvelopeResponse extends OA\Response
{
    public function __construct(
        ?string $body = null,
        bool $list = false,
        bool $paginated = false,
        array $with = [],
        ?OA\Schema $schema = null,
        ?string $description = null,
        int|string $response = 200,
    ) {
        parent::__construct(
            response   : $response,
            description: $description ?? 'Always HTTP 200: check `success` and `code`.',
            content    : new OA\JsonContent(allOf: [
                new OA\Schema(ref: '#/components/schemas/LiteApiLegacyResponse'),
                new OA\Schema(properties: [self::body($body, $list, $paginated, $with, $schema)], type: 'object'),
            ]),
        );
    }

    private static function body(?string $body, bool $list, bool $paginated, array $with, ?OA\Schema $schema): OA\Property
    {
        if ($schema !== null) {
            return new OA\Property(property: 'body', allOf: [$schema]);
        }

        $ref = $body === null ? null : '#/components/schemas/' . $body;

        if ($paginated) {
            return new OA\Property(property: 'body', allOf: [
                new OA\Schema(ref: '#/components/schemas/LiteApiPage'),
                new OA\Schema(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: $ref))], type: 'object'),
            ]);
        }

        if ($list) {
            return new OA\Property(property: 'body', type: 'array', items: new OA\Items(ref: $ref));
        }

        if ($ref === null) {
            return new OA\Property(property: 'body', properties: $with, type: 'object');
        }

        return $with
            ? new OA\Property(property: 'body', allOf: [new OA\Schema(ref: $ref), new OA\Schema(properties: $with, type: 'object')])
            : new OA\Property(property: 'body', ref: $ref);
    }
}
