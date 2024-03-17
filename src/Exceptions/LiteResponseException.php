<?php

namespace Nycorp\LiteApi\Exceptions;

use Exception;
use Nycorp\LiteApi\Response\DefResponse;

class LiteResponseException extends Exception
{
    protected $code;

    protected mixed $data;

    protected $message;

    /**
     * LiteResponseException constructor.
     *
     * @param  array|null  $data
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
}
