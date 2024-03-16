<?php

namespace Nycorp\LiteApi\Http\Controllers\Auth;


use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtpController extends \Nycorp\LiteApi\Http\Controllers\Core\OtpController
{
    protected string $username = "phone";

    /**
     *  * @OA\Post(
     *     path="/auth/otp/claim",
     *   tags={"User - Auth"},
     *   summary="Send otp for verification",
     *   description="User should exist in the systeme",
     *   operationId="sendOtp",
     *   @OA\Parameter(
     *     name="phone",
     *     required=true,
     *     in="query",
     *     description="The user phone",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     @OA\Schema(type="string"),
     *     )
     *
     * )
     * @param Request $request
     *
     * @return array|JsonResponse
     * @throws \Exception
     */
    public function push(Request $request): JsonResponse
    {
        return parent::push($request);
    }

    function getAccount($username): BaseModel
    {
        return User::where($this->username, $username)->first();
    }

    protected function validator(&$data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            $this->username => ['required', $this->username == "email" ? 'email' : "", 'max:255', 'exists:users,' . $this->username],
        ]);
    }

    /**
     * @OA\Post (
     *     path="/auth/password/reset",
     *     summary="Create user new password",
     *     operationId="resetPasswordUser",
     *     tags={"User - Auth"},
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         required=true,
     *         description="Phone",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="Reset code",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         required=true,
     *         description="User password",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="password_confirmation",
     *         in="query",
     *         required=true,
     *         description="User password",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expected response to a valid request"
     *     ),
     *     @OA\Response(
     *         response="default",
     *         description="unexpected error"
     *     )
     * )
     * @param Request $request
     * @return array|JsonResponse
     */
    public function onCheckSuccess(Request $request)
    {
        return  $this->resetPassword($request);
    }
}
