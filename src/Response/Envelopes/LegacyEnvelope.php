<?php

namespace Nycorp\LiteApi\Response\Envelopes;

use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Response\Builder;

/**
 * Historical lite-api format: always HTTP 200, outcome in body.success / body.code.
 */
class LegacyEnvelope implements ResponseEnvelope
{
    public function respond(int $code, mixed $data = null, ?string $message = null, ?string $token = null): JsonResponse
    {
        $builder = new Builder($code, $message);
        $builder->setData($data);
        $builder->setToken($token);

        return response()->json($builder->reply(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function schemas(): ?string
    {
        return dirname(__DIR__, 2) . '/Docs/LegacyEnvelopeSchemas.php';
    }
}
