<?php

namespace Nycorp\LiteApi\Http\Controllers\Auth;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountVerificationController extends \Nycorp\LiteApi\Http\Controllers\Core\OtpController
{
    /**
     * @OA\Post (
     *     path="/nowhere/auth/verify/account",
     *     summary="Verify account",
     *     operationId="verifyAccount",
     *     tags={"User - Auth"},
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
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="Reset code",
     *
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         required=true,
     *         description="User password",
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
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function onCheckSuccess(Request $request)
    {
        return $this->verifyAccount($request);
    }

    protected function checkRules(): array
    {
        return [
            $this->username => 'required',
            'code' => 'required',
        ];
    }

    public function getRecord($username): Model
    {
        return User::where($this->username, $username)->first();
    }
}
