<?php

use Illuminate\Http\JsonResponse;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Response\DefResponse;

it('reads the Legacy envelope', function () {
    $success = new DefResponse(new JsonResponse(['success' => true, 'message' => 'Request success.', 'code' => 1000, 'body' => ['id' => 1]]));
    $failure = new DefResponse(new JsonResponse(['success' => false, 'message' => 'Not found.', 'code' => 1007, 'body' => null, 'trace_id' => 'x']));

    expect($success->isSuccess())->toBeTrue()
        ->and($success->getBody())->toBe(['id' => 1])
        ->and($success->getData())->toBe(['id' => 1])
        ->and($success->getMessage())->toBe('Request success.')
        ->and($success->getCode())->toBe(1000)
        ->and($failure->isSuccess())->toBeFalse()
        ->and($failure->getCodeKey())->toBe(ResponseCode::REQUEST_NOT_FOUND);
});

it('reads the Standard envelope', function () {
    $success = new DefResponse(new JsonResponse(['data' => ['id' => 1], 'token' => 'abc']));
    $failure = new DefResponse(new JsonResponse(
        ['error' => ['code' => 1002, 'type' => 'REQUEST_VALIDATION_ERROR', 'message' => 'The name field is required.', 'details' => ['name' => ['required']], 'trace_id' => 'x']],
        422
    ));

    expect($success->isSuccess())->toBeTrue()
        ->and($success->getBody())->toBe(['id' => 1])
        ->and($success->getData())->toBe(['id' => 1])
        ->and($success->getCode())->toBe(1000)
        ->and($success->getCodeKey())->toBe(ResponseCode::REQUEST_SUCCESS)
        ->and($success->getMessage())->toBe('')
        ->and($failure->isSuccess())->toBeFalse()
        ->and($failure->getBody())->toBe(['name' => ['required']])
        ->and($failure->getMessage())->toBe('The name field is required.')
        ->and($failure->getCode())->toBe(1002)
        ->and($failure->getCodeKey())->toBe(ResponseCode::REQUEST_VALIDATION_ERROR)
        ->and($failure->isCodeSame(ResponseCode::REQUEST_VALIDATION_ERROR))->toBeTrue();
});

it('turns a Standard failure into the same LiteResponseException', function () {
    $exception = \Nycorp\LiteApi\Exceptions\LiteResponseException::parse(new DefResponse(new JsonResponse(
        ['error' => ['code' => 1005, 'type' => 'REQUEST_NOT_AUTHORIZED', 'message' => 'Name refused', 'details' => null, 'trace_id' => 'x']],
        403
    )));

    expect($exception->getCode())->toBe(ResponseCode::REQUEST_NOT_AUTHORIZED)
        ->and($exception->getMessage())->toBe('Name refused');
});
