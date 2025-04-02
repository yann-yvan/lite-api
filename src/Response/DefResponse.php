<?php

namespace Nycorp\LiteApi\Response;

use Illuminate\Http\JsonResponse;

class DefResponse
{
    private mixed $data;

    private JsonResponse $response;

    /**
     * DefResponse constructor.
     */
    public function __construct(JsonResponse $response)
    {
        $this->response = $response;
        $this->data = $response->getData(true);
    }

    /**
     * Get data
     */
    public function getData(): mixed
    {
        return $this->data[Builder::BODY];
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
        return $this->data[Builder::SUCCESS];
    }

    public function getMessage(): string
    {
        return $this->data[Builder::MESSAGE];
    }

    public function getCodeKey(): string
    {
        return Builder::getKeyByCode($this->getCode());
    }

    public function getCode(): int
    {
        return $this->data[Builder::CODE];
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
        return $this->data['body'];
    }

}
