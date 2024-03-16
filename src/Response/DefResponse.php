<?php


namespace Nycorp\LiteApi\Response;


use Illuminate\Http\JsonResponse;

class DefResponse
{
    private mixed $data;
    private JsonResponse $response;

    /**
     * DefResponse constructor.
     * @param JsonResponse $response
     */
    public function __construct(JsonResponse $response)
    {
        $this->response = $response;
        $this->data = $response->getData(true);
    }

    /**
     * Get data
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data['body'];
    }

    /**
     * Check if the response is a success
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->data['status'];
    }

    public function getMessage(): string
    {
        return $this->data['message'];
    }

    public function getCodeKey(): string
    {
        return Builder::getKeyByCode($this->getCode());
    }

    public function getCode(): int
    {
        return $this->data['code'];
    }

    public function getResponse(): JsonResponse
    {
        return $this->response;
    }
}
