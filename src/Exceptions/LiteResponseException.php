<?php

namespace Nycorp\LiteApi\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Response\DefResponse;
use Nycorp\LiteApi\Traits\ApiResponseTrait;

class LiteResponseException extends Exception
{
    use ApiResponseTrait;

    protected $code;

    protected mixed $data;

    protected $message;

    /**
     * LiteResponseException constructor.
     *
     * @param array|null $data
     */
    public function __construct(int $code, string $message, mixed $data = null)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    public static function parse(DefResponse $response): LiteResponseException
    {
        return new LiteResponseException($response->getCodeKey(), $response->getMessage(), $response->getData());
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function toResponse(): JsonResponse
    {
        return self::liteResponse($this->code, $this->data, $this->message);
    }

    public function isCodeSame(int $code): bool
    {
        return $this->getCode() === abs($code);
    }

}
