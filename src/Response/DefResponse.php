<?php

namespace Nycorp\LiteApi\Response;

use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Models\ResponseCode;

/**
 * Reads back a lite-api response, whatever its envelope:
 *  Legacy   : {"success", "message", "code", "body"}
 *  Standard : {"data"} or {"error": {"code", "type", "message", "details"}}
 */
class DefResponse
{
    private mixed $data;

    private JsonResponse $response;

    /**
     * Normalised to the Legacy keys, so every getter works for both envelopes
     */
    private array $normalized;

    /**
     * DefResponse constructor.
     */
    public function __construct(JsonResponse $response)
    {
        $this->response = $response;
        $this->data = $response->getData(true);
        $this->normalized = self::normalize($this->data);
    }

    private static function normalize(mixed $data): array
    {
        $data = is_array($data) ? $data : [];

        if (array_key_exists(Builder::SUCCESS, $data)) {
            return $data;
        }

        if (isset($data['error']) && is_array($data['error'])) {
            return [
                Builder::SUCCESS => false,
                Builder::MESSAGE => (string)($data['error']['message'] ?? ''),
                Builder::CODE    => (int)($data['error']['code'] ?? abs(ResponseCode::REQUEST_FAILURE)),
                Builder::BODY    => $data['error']['details'] ?? null,
            ];
        }

        // Standard success: no code nor message in the body
        return [
            Builder::SUCCESS => true,
            Builder::MESSAGE => '',
            Builder::CODE    => ResponseCode::REQUEST_SUCCESS,
            Builder::BODY    => $data['data'] ?? null,
        ];
    }

    /**
     * @deprecated
     * Get data
     */
    public function getData(): mixed
    {
        return $this->normalized[Builder::BODY];
    }

    static function parse($data): DefResponse
    {
        if (is_array($data)) {
            $data = new JsonResponse($data);
        }
        return new DefResponse($data);
    }

    /**
     * Check if the response is a success
     */
    public function isSuccess(): bool
    {
        return (bool)$this->normalized[Builder::SUCCESS];
    }

    public function getMessage(): string
    {
        return (string)$this->normalized[Builder::MESSAGE];
    }

    public function getCodeKey(): int
    {
        return Builder::getOriginalCode($this->getCode());
    }

    public function getCode(): int
    {
        return $this->normalized[Builder::CODE];
    }

    public function getResponse(): JsonResponse
    {
        return $this->response;
    }

    /**
     * Get data
     * @return mixed
     */
    public function getBody()
    {
        return $this->normalized[Builder::BODY];
    }

    public function isCodeSame(int $code): bool
    {
        return abs($this->getCode()) === abs($code);
    }

}
