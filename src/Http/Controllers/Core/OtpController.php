<?php

namespace Nycorp\LiteApi\Http\Controllers\Core;

use App\Notification\AuthenticatorNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Nycorp\LiteApi\Http\Controllers\Auth\LoginController;
use Nycorp\LiteApi\Models\Authenticator;
use Nycorp\LiteApi\Response\DefResponse;

abstract class OtpController extends CoreController
{
    protected string $username = 'email';

    protected int $ttlInMin = 60;

    public function push(Request $request): JsonResponse
    {
        $data = $request->all($this->username);

        $validator = $this->validator($data);
        if ($validator->fails()) {
            return $this->liteResponse(config('lite-api-code.request.validation_error'), $validator->errors(), 'Account not found');
        }

        try {
            $model = $this->getRecord($data[$this->username]);
            $data['username'] = $data[$this->username];
            $data['code'] = random_int(100000, 999999);
            $data['token'] = Hash::make($data[$this->username]);
            $data['model'] = get_class($model);
            $data['created_at'] = Carbon::now();
            self::destroy($data);
            $this->create($data);
            Notification::send($model, new AuthenticatorNotification($data['token'], $data['code']));

            return $this->liteResponse(config('lite-api-code.request.success'));
        } catch (Exception $exception) {
            return $this->liteResponse(config('lite-api-code.request.failure'), null, $exception->getMessage());
        }
    }

    abstract public function getRecord($username): Model;

    /**
     * Destroy reset in database
     */
    public static function destroy(array $data)
    {
        Authenticator::where('model', $data['model'])->where('username', $data['username'])->delete();
    }

    /**
     * Change user password
     *
     * @throws Exception
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->all([$this->username, 'password']);

        $model = $this->getRecord($data[$this->username]);

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

    public function check(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, $this->checkRules());

        if ($validator->fails()) {
            return $this->liteResponse(config('lite-api-code.request.validation_error'), $validator->errors());
        }

        try {
            //get unexpired token
            $resetPassword = self::getModel()::where('code', $data['code'])->where('username', $data[$this->username])
                ->where('created_at', '>', Carbon::now()->subMinutes($this->ttlInMin))
                ->first();

            //check if code exist
            if ($resetPassword === null) {
                return $this->liteResponse(config('lite-api-code.request.expired'));
            }

            //Run custom action
            $resetResult = new DefResponse($this->onCheckSuccess($request));
            if ($resetResult->isSuccess()) {
                //Drop Reset record
                self::destroy($resetPassword->toArray());

                //Make something like login
                return self::goto($request);
            } else {
                return $resetResult->getResponse();
            }
        } catch (Exception $exception) {
            return $this->liteResponse(config('lite-api-code.request.failure'), $exception->getFile(), $exception->getMessage());
        }
    }

    public function getModel(): Model
    {
        return new Authenticator;
    }

    public function updateRule(mixed $modelId): array
    {
        return [];
    }

    public function addRule(): array
    {
        return [];
    }

    protected function checkRules(): array
    {
        return [
            $this->username => 'required|max:1',
            'code' => 'required',
            'password' => 'required|confirmed|min:6',
        ];
    }

    abstract public function onCheckSuccess(Request $request);

    public function goto(Request $request): JsonResponse
    {
        //Login the user
        return (new LoginController())->login($request);
    }

    /**
     * Change user password
     *
     * @throws Exception
     */
    public function verifyAccount(Request $request): JsonResponse
    {
        $data = $request->all([$this->username]);

        $model = $this->getRecord($data[$this->username]);

        if (empty($model)) {
            return $this->liteResponse(config('lite-api-code.token.user_not_found'));
        }

        try {
            if ($model->verified_at == null) {
                $model->update([
                    'verified_at' => Carbon::now(),
                ]);
            }

            return $this->liteResponse(config('lite-api-code.request.success'));
        } catch (\Exception $exception) {
            return $this->liteResponse(config('lite-api-code.request.failure'), null, $exception->getMessage());
        }
    }
}
