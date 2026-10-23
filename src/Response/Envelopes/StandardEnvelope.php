<?php

namespace Nycorp\LiteApi\Response\Envelopes;

use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Response\Builder;
use Nycorp\LiteApi\Response\HttpStatusMap;
use Nycorp\LiteApi\Response\ResponseCodeCatalog;

/**
 * Real HTTP statuses.
 *  success : {"data": ..., "token": "..." (auth routes only)}
 *  failure : {"error": {"code", "type", "message", "details", "trace_id"}}
 */
class StandardEnvelope implements ResponseEnvelope
{
    public function __construct(private readonly HttpStatusMap $statuses)
    {
    }

    public function respond(int $code, mixed $data = null, ?string $message = null, ?string $token = null): JsonResponse
    {
        // Builder still validates the code and provides the default message and trace id
        $reply = (new Builder($code, $message))->reply();

        if ($reply[Builder::SUCCESS]) {
            $payload = ['data' => $data];

            if ($token !== null) {
                $payload['token'] = $token;
            }
        } else {
            $payload = [
                'error' => [
                    'code'     => $reply[Builder::CODE],
                    'type'     => ResponseCodeCatalog::nameOf($code),
                    'message'  => $reply[Builder::MESSAGE],
                    'details'  => $data,
                    'trace_id' => $reply['trace_id'] ?? null,
                ],
            ];
        }

        return response()->json($payload, $this->statuses->for($code), [], JSON_UNESCAPED_SLASHES);
    }

    public static function schemas(): ?string
    {
        return dirname(__DIR__, 2) . '/Docs/StandardEnvelopeSchemas.php';
    }
}
