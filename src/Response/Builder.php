<?php


namespace Nycorp\LiteApi\Response;


use Exception;
use Illuminate\Support\Str;

class Builder
{
    /*
       * Class properties
       */
    private null|string $message = null;
    private bool $status = false;
    private int $code = 0;
    private mixed $data = null;
    private null|string $token = null;

    /**
     * Code constructor.
     *
     * @param      $code
     * @param null $message
     *
     * @throws Exception
     */
    public function __construct($code, $message = null)
    {
        if ($this->isNotDocCode($code)) {
            throw new Exception('Response code not found please refer to documentation');
        }
        $this->status = $code > 0;
        $this->code = abs($code);
        $this->message = $this->defaultMessage($code, $message);
    }

    /**
     * Check if send code exist in doc code
     *
     * @param  $code
     *
     * @return bool
     */
    private function isNotDocCode($code): bool
    {
        $codes = array();
        foreach (config('lite-api-code') as $item => $value) {
            $codes = array_merge($codes, array_values($value));
        }
        return !in_array($code, $codes);
    }

    private function defaultMessage($code, $message): string
    {
        if (empty($message)) {
            foreach (config('lite-api-code') as $item => $value) {
                foreach ($value as $key => $val)
                    if ($val == $code) {
                        return Str::upper($item) . ' ' . $key;
                    }
            }
        }

        return $message;
    }

    public static function getKeyByCode($code): string
    {
        foreach (config('lite-api-code') as $values) {
            foreach ($values as $value) {
                if (abs($value) == $code) {
                    return $value;
                }
            }
        }
        return config("code.request.failure");
    }

    /**
     * @param mixed $data
     */
    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return array
     */
    public function reply(): array
    {
        $data = [
            'status' => $this->status,
            'message' => $this->message,
            'code' => $this->code,
            'body' => $this->data,
        ];
        if ($this->token != null) {
            $data['token'] = $this->token;
        }

        return $data;
    }
}
