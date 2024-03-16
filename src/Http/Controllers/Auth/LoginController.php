<?php


namespace Nycorp\LiteApi\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Nycorp\LiteApi\Http\Traits\ApiResponseTrait;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Post(
     *     path="/api/login",
     *   tags={"User - Auth"},
     *   summary="Logs user into the system",
     *   description="Generate user token by login in the systeme",
     *   operationId="login",
     *   @OA\Parameter(
     *     name="email",
     *     required=true,
     *     in="query",
     *     description="The user name for login max:60",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="password",
     *     in="query",
     *     required=true,
     *     @OA\Schema(
     *         type="string",
     *     ),
     *     description="The password for login in clear text min:6, max:20",
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     @OA\Schema(type="string"),
     *   ),
     * )
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $validator = Validator::make($credentials, [
            'email' => 'bail|required|email|max:60',
            'password' => 'bail|required|min:6|max:20',
        ]);

        if ($validator->fails())
            return $this->liteResponse(config("lite-api-code.request.validation_error"), $validator->errors());

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->liteResponse(config('lite-api-code.auth.wrong_credentials'), null, 'Invalid email or password');
            }
            JWTAuth::setToken($token);
            $user = JWTAuth::toUser();
        } catch (Exception $e) {
            return $this->liteResponse(config('lite-api-code.request.failure'), null, $e->getMessage());
        }
        return $this->liteResponse(config('lite-api-code.request.success'), $user, null, $token);
    }

    /**
     * * @OA\Post(
     *     path="/api/logout",
     *   tags={"User - Auth"},
     *   summary="Logout user from system",
     *   description="Disconnecting user to the system by destroying his token session",
     *   operationId="logoutUser",
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     @OA\Schema(type="string"),
     *   ),
     * )
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        JWTAuth::parseToken()->invalidate();
        return $this->liteResponse(config('lite-api-code.request.success'));
    }
}
