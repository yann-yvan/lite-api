<?php

namespace Nycorp\LiteApi\Response\Envelopes;

use Illuminate\Http\JsonResponse;

/**
 * Shape of the JSON responses of an API version. liteResponse() delegates to the envelope
 * of the current version, so controllers stay identical whatever the envelope.
 */
interface ResponseEnvelope
{
    public function respond(int $code, mixed $data = null, ?string $message = null, ?string $token = null): JsonResponse;

    /**
     * File holding the OpenAPI schemas of this envelope, added to the versioned documentation
     */
    public static function schemas(): ?string;
}
