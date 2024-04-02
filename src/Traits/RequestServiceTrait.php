<?php

namespace Nycorp\LiteApi\Traits;


use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait RequestServiceTrait
{
    use ApiResponseTrait;

    /**
     * Claim authorization to access remote service
     *
     * @param array $payload
     * @param string $baseUrl
     * @param string $endpoint
     * @param bool $async
     * @param string $method
     * @param array $headers
     * @return array|JsonResponse
     * @throws \Exception
     */
    public function consume(array $payload, string $baseUrl, string $endpoint, bool $async = false, string $method = "post", array $headers = []): JsonResponse|array
    {
        $http = Http::baseUrl($baseUrl)
            ->async($async)
            ->withHeaders(array_merge([
                'Access-Control-Allow-Origin' => encrypt(request()->url()),
                'Accept-Language' => app()->getLocale(),
            ], $headers));

        # Add all files to request
        foreach (request()->allFiles() as $key => $file) {
            $http->attach($key, fopen($file->getRealPath(), 'r'), $file->getClientOriginalName(), ['Accept' => $file->getClientMimeType()]);
        }

        Log::channel('daily')->debug(($async ? "**async** " : "") . "$method $baseUrl/$endpoint");

        if ($async) {
            $http->{$method}($endpoint, $payload)->then(function ($response) use ($method, $baseUrl, $endpoint) {
                Log::channel('daily')->debug("**async** $method $baseUrl/$endpoint response");
            });

            return self::liteResponse(config('lite-api-code.request.success'));
        }

        try {
            $response = $http->{$method}($endpoint, $payload);
        } catch (Exception|\Throwable $exception) {
            Log::channel('daily')->emergency($exception->getMessage(), $exception->getTrace());
            return self::liteResponse(code: config('lite-api-code.request.emergency'), message: $exception->getMessage());
        }


        if ($response->successful()) {
            Log::channel('daily')->debug("$method $baseUrl/$endpoint", ["headers" => $headers, "payload" => $payload, 'response' => $response->json()]);
            return response()->json($response->json());
        }

        return self::liteResponse(config('lite-api-code.request.service_not_available'), [
            "context" => $http,
            "remote" => $response,
        ]);
    }

}
