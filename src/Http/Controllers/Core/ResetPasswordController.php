<?php

namespace Nycorp\LiteApi\Http\Controllers\Core;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Nycorp\LiteApi\Http\Controllers\Auth\LoginController;
use Nycorp\LiteApi\Models\Authenticator;
use Nycorp\LiteApi\Response\DefResponse;

abstract class ResetPasswordController extends CoreController
{
    public function reset(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return $this->liteResponse(config('lite-api-code.request.validation_error'), $validator->errors());
        }

        try {
            //get unexpired token
            $resetPassword = self::getModel()::where('code', $data['code'])->where('email', $data['email'])
                ->where('created_at', '>', Carbon::now()->subHours(2))
                ->first();

            //check if code exist
            if ($resetPassword === null) {
                return $this->liteResponse(config('lite-api-code.request.expired'));
            }

            //update user password
            $resetResult = new DefResponse($this->resetPassword($request));
            if ($resetResult->isSuccess()) {
                //Drop Reset record
                OtpController::destroy($resetPassword->toArray());

                //Login the user
                return (new LoginController())->login($request);
            } else {
                return $resetResult->getResponse();
            }
        } catch (Exception $exception) {
            return $this->liteResponse(config('lite-api-code.request.failure'), null, $exception->getMessage());
        }
    }

    protected function rules()
    {
        return [
            'email' => 'required',
            'code' => 'required',
            'password' => 'required|confirmed|min:6',
        ];
    }

    public function getModel(): Model
    {
        return new Authenticator;
    }

    /**
     * Change user password
     *
     *
     * @throws Exception
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->all(['email', 'password']);

        $model = $this->getModel($data['email']);
        if (empty($model)) {
            return $this->liteResponse(config('lite-api-code.token.user_not_found'));
        }

        try {
            if (Hash::check($data['password'], $model->password)) {
                return $this->liteResponse(config('lite-api-code.request.failure'), null, 'Please use a different password from the current');
            }
            $model->update([
                'password' => Hash::make($data['password']),
            ]);

            return $this->liteResponse(config('lite-api-code.request.success'));
        } catch (\Exception $exception) {
            return $this->liteResponse(config('lite-api-code.request.failure'), null, $exception->getMessage());
        }
    }

    abstract public function getRecord($email): Model;

    public function addRule(): array
    {
        return [];
    }

    public function updateRule(mixed $modelId): array
    {
        return [];
    }
}
