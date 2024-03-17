<?php

namespace Nycorp\LiteApi\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Http\Controllers\Core\CoreController;
use Nycorp\LiteApi\Response\DefResponse;

class RegisterController extends CoreController
{
    protected bool $rollbackOnAddNotificationFailed = true;

    /**
     * {@inheritDoc}
     */
    public function getModel(): Model
    {
        return new User;
    }

    /**
     * @OA\Post (
     *     path="/nowhere/auth/register",
     *     summary="Create new user account",
     *     operationId="register",
     *     tags={"User - Auth"},
     *
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         required=false,
     *         description="Lastname",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         required=false,
     *         description="Firstname",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         required=false,
     *         description="Email",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         required=true,
     *         description="Password",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         required=true,
     *         description="Phone",
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Expected response to a valid request"
     *     ),
     *     @OA\Response(
     *         response="default",
     *         description="unexpected error"
     *     )
     * )
     *
     * @return array|JsonResponse
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * {@inheritDoc}
     */
    public function updateRule(mixed $modelId): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function addRule(): array
    {
        return [
            "name" => ['nullable', 'string', 'min:3'],
            #User::LASTNAME => ['nullable', 'string', 'min:3'],
            "password" => ['required', 'min:6'],
            "email" => ['nullable', 'email'],
            #User::PHONE => ['required', 'unique:users,phone', 'numeric', 'min:9'],
        ];
    }

    /**
     * @throws LiteResponseException
     */
    public function onAfterAdd(Model $model)
    {
        $response = new DefResponse((new OtpController())->push(\request()->merge(['phone' => $model->phone])));
        if (! $response->isSuccess()) {
            throw LiteResponseException::parse($response);
        }
    }
}
