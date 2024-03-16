<?php


namespace Nycorp\LiteApi\Http\Controllers\Core;


use App\Notification\ResetPasswordNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Nycorp\LiteApi\Http\Controllers\Auth\LoginController;
use Nycorp\LiteApi\Models\LiteApiModel;
use Nycorp\LiteApi\Models\ResetPassword;
use Nycorp\LiteApi\Response\DefResponse;

abstract class OtpController extends CoreController
{
    protected string $username = "email";
    protected int $ttlInMin = 60;

    public function push(Request $request): JsonResponse
    {
        $data = $request->all($this->username);

        $validator = $this->validator($data);
        if ($validator->fails()) {
            return $this->liteResponse(config('lite-api-code.request.validation_error'), $validator->errors(), "Account not found");
        }

        try {
            $model = $this->getAccount($data[$this->username]);
            $data['username'] = $data[$this->username];
            $data['code'] = random_int(100000, 999999);
            $data['token'] = Hash::make($data[$this->username]);
            $data['model'] = get_class($model);
            $data['created_at'] = Carbon::now();
            self::destroy($data);
            $this->create($data);
            Notification::send($model, new ResetPasswordNotification($data['token'], $data['code']));
            return $this->liteResponse(config('lite-api-code.request.success'));
        } catch (Exception $exception) {
            return $this->liteResponse(config('lite-api-code.request.failure'), null, $exception->getMessage());
        }
    }

    abstract function getAccount($username): LiteApiModel;

    /**
     * Destroy reset in database
     *
     * @param array $data
     */
    public function destroy(array $data)
    {
        ResetPassword::where('model', $data['model'])->where("username", $data["username"])->delete();
    }

    public function create(array $data): Model
    {
        return ResetPassword::create($data);
    }

    /**
     * Change user password
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->all([$this->username, 'password']);

        $model = $this->getAccount($data[$this->username]);

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
            $resetPassword = ResetPassword::where('code', $data['code'])->where('username', $data[$this->username])
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

    public function getModel(): LiteApiModel
    {
        // TODO: Implement getModel() method.
    }

    public function updateRule($modelId): array
    {
        // TODO: Implement updateRule() method.
    }

    public function addRule(): array
    {
        // TODO: Implement addRule() method.
    }

    protected function checkRules(): array
    {
        return [
            $this->username => 'required|max:1',
            'code' => 'required',
            'password' => 'required|confirmed|min:6',
        ];
    }

    public abstract function onCheckSuccess(Request $request);

    public function goto(Request $request): JsonResponse
    {
        //Login the user
        return (new LoginController())->login($request);
    }

    /**
     * Change user password
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function verifyAccount(Request $request): JsonResponse
    {
        $data = $request->all([$this->username]);

        $model = $this->getAccount($data[$this->username]);

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
