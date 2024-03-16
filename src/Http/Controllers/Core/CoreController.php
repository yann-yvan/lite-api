<?php


namespace Nycorp\LiteApi\Http\Controllers\Core;


use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Http\Traits\ApiResponseTrait;
use Nycorp\LiteApi\Response\DefResponse;

abstract class CoreController
{
    use ApiResponseTrait;

    public const ROUTE_ADD = "add";
    public const ROUTE_UPDATE = "update";
    public const ROUTE_DELETE = "delete";
    public const ROUTE_SEARCH = "search";
    const ROOT_DIRECTORY = "upload";
    protected array $excludedUpdateAttributes = [];
    protected int $pagination = 50;
    protected string $key = "id";
    protected bool $rollbackOnAddNotificationFailed = false;
    protected bool $onAddNotify = false;
    protected bool $forceDelete = false;
    protected string $searchOrderKey = "created_at";
    protected string $searchOrderBy = "orderByDesc";

    //TODO trim request data
    protected array $searchColumns = [];

    /**
     * Use this only if the controller is not in a subdirectory of Controllers
     *
     * @param array $exclude
     */
    public static function expose(array $exclude = []): void
    {
        $class = get_called_class();
        if (!in_array(self::ROUTE_ADD, $exclude)) {
            Route::post(self::ROUTE_ADD, "$class@add");
        }

        if (!in_array(self::ROUTE_UPDATE, $exclude)) {
            Route::patch(self::ROUTE_UPDATE, "$class@update");
            Route::put(self::ROUTE_UPDATE, "$class@update");
        }

        if (!in_array(self::ROUTE_SEARCH, $exclude)) {
            Route::get(self::ROUTE_SEARCH, "$class@search");
        }

        if (!in_array(self::ROUTE_DELETE, $exclude)) {
            Route::delete(self::ROUTE_DELETE, "$class@delete");
        }
    }

    public function delete(Request $request): JsonResponse|array
    {
        try {
            $model = $this->getModel()->where($this->key, $request->id)->first();
            if (empty($model)) {
                return self::liteResponse(config("lite-api-code.request.not_found"));
            }

            $this->onBeforeDelete($model);

            $this->forceDelete ? $model->forceDelete() : $model->delete();

            $this->onAfterDelete($model);

            return self::liteResponse(config("lite-api-code.request.success"));
        } catch (LiteResponseException $exception) {
            return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
        } catch (\Exception $exception) {
            return self::liteResponse(config("lite-api-code.request.exception"), null, $exception->getMessage());
        }
    }

    /**
     * @return Model the model to manipulate
     */
    abstract function getModel(): Model;

    /**
     * Fire before deleting the model
     *
     * @param Model $model the record that will be deleted
     */
    public function onBeforeDelete(Model $model)
    {
    }

    /**
     * Fire after the model has been deleted
     *
     * @param Model $model
     */
    public function onAfterDelete(Model $model)
    {
    }

    public static function sendSms($phone, $text)
    {
        return Http::get("http://51.195.252.172:13002/cgi-bin/sendsms",
            [
                "username" => "nycorp",
                "password" => "test",
                "from" => env("APP_NAME"),
                "to" => $phone,
                "text" => $text
            ]);
    }

    /**
     * Record a model according to his fillable and assets attributes
     *
     * @param Request $request the request query containing all parameter
     *
     * @return array|JsonResponse
     * @throws \Exception
     */
    public function add(Request $request)
    {
        $data = $request->all($this->getModel()->getFillable());

        if (array_key_exists("phone", $data)) {
            $data["phone"] = $this->cleanPhone($data["phone"]);
        }

        $password = null;
        if (array_key_exists("password", $data) and empty($data["password"])) {
            $password = Str::random(8);
            $data["password"] = $password;
        } elseif (array_key_exists("password", $data)) {
            $password = $data["password"];
        }

        //make specific data transformation or custom validation
        try {
            $this->onBeforeAdd($data);
        } catch (LiteResponseException $exception) {
            return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
        } catch (\Exception $exception) {
            return self::liteResponse(config("lite-api-code.request.exception"), null, $exception->getMessage());
        }

        $model = $this->getModel();
        //save all file
        foreach ($model->getAssets() as $asset) {
            if (!is_string($data[$asset])) {
                $data[$asset] = $this->storeFile($asset, $this->getFileDirectory());
            }
        }

        //save model
        $response = new DefResponse($this->save($data));
        if ($response->isSuccess()) {
            if ($this->onAddNotify) {
                try {
                    if (array_key_exists("verified_at", $data) and empty($data["verified_at"])) {
                        Notification::send($this->getModel()->where($this->key, $response->getData()[$this->key])->first(), $this->getNotification($data, $password));
                    } else {
                        if (!empty($password)) {
                            //Send mail notification to newly created account if user has password
                            Notification::send($this->getModel()->where($this->key, $response->getData()[$this->key])->first(), $this->getNotification($data, $password));
                        }
                    }
                } catch (\Exception|\Throwable $exception) {
                    $this->rollbackAdd($response);
                    if ($exception instanceof LiteResponseException) {
                        return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
                    } else {
                        return self::liteResponse(config("lite-api-code.request.exception"), null, $exception->getMessage());
                    }
                }
            }
        } else {
            $this->rollbackAdd($response);
        }

        return $response->getResponse();
    }

    public function cleanPhone($phone)
    {
        $phone = str_replace("+", "", $phone);
        $phone = str_replace("237", "", $phone);
        return $phone;
    }

    /**
     * Fire before inserting the new record
     *
     * @param array $data the get from request with out any change on field except for email and password
     */
    public function onBeforeAdd(array &$data): void
    {

    }

    public function storeFile($fileInputName, $directory = self::ROOT_DIRECTORY)
    {
        $file = request()->file($fileInputName);
        $mediaSet = [];
        if (is_array($file)) {
            foreach ($file as $key => $fileItem) {
                array_push($mediaSet, $this->saveMedia($fileItem, $directory, $key));
            }
            return json_encode($mediaSet);
        } else {
            return $this->saveMedia($file, $directory);
        }
    }

    public function saveMedia($file, $directory = self::ROOT_DIRECTORY, $suffix = '')
    {
        if (empty($file)) {
            return null;
        }

        $path = join('/', [self::ROOT_DIRECTORY, Str::replaceLast('/', '', $directory)]);
        $name = hrtime(true) . "$suffix." . strtolower($file->getClientOriginalExtension());
        return $file->move($path, $name)->getPathname();
    }

    /**
     * get the upload directory for file of the current model
     *
     * @return string the directory where files will be saved
     */
    public function getFileDirectory(): string
    {
        return strtolower(getClassName($this->getModel()));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param array $data
     * @return JsonResponse
     * @throws Exception
     */
    public function save(array $data): JsonResponse
    {
        if (array_key_exists("uuid", $data)) {
            $data["uuid"] = Str::uuid();
        }

        $validator = $this->validator($data);
        if ($validator->fails()) {
            return self::liteResponse(config('lite-api-code.request.validation_error'), $validator->errors());
        }

        try {
            $model = $this->create($data);
            $this->saved($model);
            $response = new DefResponse(self::liteResponse(config('lite-api-code.request.success'), $model));
            return $response->getResponse();
        } catch (LiteResponseException $exception) {
            return self::liteResponse($exception->getCode(), array_merge($exception->getData() ?? [], $model->toArray()), $exception->getMessage());
        } catch (\Exception $exception) {
            return self::liteResponse(config("lite-api-code.request.exception"), null, $exception->getMessage());
        }
    }

    /**
     * Default validator in case of non specification
     *
     * @param $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(&$data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, $this->addRule());
    }

    /**
     * Validation for model creation
     *
     *
     * @return array and array of validation
     */
    abstract function addRule(): array;

    /**
     * Record the new model
     *
     * @param array $data all required fields that should be record
     *
     * @return Model
     */
    public function create(array $data): Model
    {
        if (array_key_exists("password", $data)) {
            $data["password"] = Hash::make($data["password"]);
        }
        return $this->getModel()::firstOrCreate($data);
    }

    public function saved($model)
    {
        $model->refresh();
        $this->onAfterAdd($model);
    }

    /**
     * Fire on model record with success
     *
     * @param Model $model the new recorded model
     */
    public function onAfterAdd(Model $model)
    {

    }

    /**
     * @param array $data contain any data stored
     * @param string $password generated for the user
     *
     * @return RegisterNotification the mailable notification to send to the new created model
     */
    public function getNotification($data, $password): RegisterNotification
    {
        return new RegisterNotification("Place email here or any login", "Place password here");
    }

    /**
     * Delete saved model
     *
     * @param \App\Http\ResponseParser\DefResponse $response
     */
    public function rollbackAdd(DefResponse $response)
    {
        //Delete saved model
        if ($this->rollbackOnAddNotificationFailed) {
            try {
                if (Arr::has($response->getData(), $this->key)) {
                    $this->getModel()->where($this->key, $response->getData()[$this->key])->forceDelete();
                }
            } catch (\Exception|\Throwable $ex) {
            }
        }
    }

    /**
     * Search account by given available input
     *
     * @param Request $request
     *
     * @return array|JsonResponse
     * @throws \Exception
     */
    public function search(Request $request)
    {
        $query = $this->getModel()::query();

        $entireText = $request->boolean("inclusive", true);
        $this->injectDefaultSearchCriteria($query, $request, $entireText);

        $this->specificSearchCriteria($query, $request);

        if ($request->has("perPage") and is_integer($request->perPage)) {
            $this->pagination = $request->perPage;
        }

        return self::liteResponse(config("lite-api-code.request.success"), $query->{$this->searchOrderBy}($this->searchOrderKey)->paginate($this->pagination));
    }

    /**
     * Add default field attribute search criteria
     *
     * @param      $query
     * @param      $request
     * @param bool $entireText
     * @param bool $entireTextOnly
     */
    public function injectDefaultSearchCriteria($query, $request, bool $entireText = true, bool $entireTextOnly = false)
    {
        $keywords = [];
        foreach ($this->getModel()->getFillable() as $input) {
            $value = $request->{$input};
            if (!empty($value)) {
                //Build query
                if (in_array($input, $this->searchColumns) or empty($this->searchColumns)) {
                    $keywords[] = $value;
                } else {
                    $constraints[$input] = $value;
                }
            }
        }

        $query->search(join(" ", $keywords), null, $entireText, $entireTextOnly);

        if (!empty($constraints)) {
            $query->where($constraints);
        }
    }

    /**
     * Add some specific search criteria
     *
     * @param $query
     * @param $request
     */
    public function specificSearchCriteria($query, $request)
    {
    }

    /**
     * Update any model according to his available field in the fillable and assets attributes
     *
     * @param Request $request
     *
     * @return array|JsonResponse
     * @throws \Exception
     */
    public function update(Request $request)
    {
        //get model fillable attributes
        $data = $request->all($this->getModel()->getFillable());

        //Clean phone
        if (array_key_exists("phone", $data)) {
            $data["phone"] = $this->cleanPhone($data["phone"]);
        }

        //save all files
        foreach ($this->getModel()->getAssets() as $asset) {
            $data[$asset] = $this->storeFile($asset, $this->getFileDirectory());
        }

        //remove empty value
        $data = array_filter($data, "strlen");
        if (empty($data)) {
            return self::liteResponse(config("lite-api-code.request.validation_error"), null, "Empty data set, no value to update");
        }

        $this->onBeforeUpdate($data);

        //verify that the specified id exists
        $model = $this->getModel()->where($this->key, $request->{$this->key})->first();
        if (empty($model)) {
            return self::liteResponse(config("lite-api-code.request.not_found"));
        }

        $this->onBeforeUpdateWithModel($data, $model);

        //This part remove attribute that should not be updated
        if (is_array($this->excludedUpdateAttributes) and !empty($this->excludedUpdateAttributes)) {
            foreach ($this->excludedUpdateAttributes as $excludedUpdateAttribute) {
                if (array_key_exists($excludedUpdateAttribute, $data)) {
                    unset($data[$excludedUpdateAttribute]);
                }
            }
        }

        //validation using the updateRule
        $validator = Validator::make($data, $this->updateRule($model->id));
        if ($validator->fails()) {
            return self::liteResponse(config("lite-api-code.request.validation_error"), $validator->errors());
        }

        try {
            //Updating model
            $this->getModel()->where($this->key, $request->{$this->key})->update($data);
            //fetch model and return it we new values
            $updatedModel = $this->getModel()->where($this->key, $request->{$this->key})->first();

            $this->onAfterUpdate($updatedModel);

            return self::liteResponse(config("lite-api-code.request.success"), $updatedModel);
        } catch (\Exception $exception) {
            if ($exception instanceof LiteResponseException) {
                return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
            } else {
                return self::liteResponse(config("lite-api-code.request.exception"), null, $exception->getMessage());
            }
        }
    }

    /**
     * Fire before updating an existing record
     *
     * @param array $data the get from request with out any change on field
     */
    public function onBeforeUpdate(array &$data): void
    {

    }

    /**
     * Fire before updating an existing record
     *
     * @param array $data the get from request with out any change on field
     * @param \App\Models\Model $model
     */
    public function onBeforeUpdateWithModel(array &$data, Model $model): void
    {

    }

    /**
     * Validation for model update
     *
     * @param mixed $modelId of the model about to be updated
     *
     * @return array and array of validation
     */
    abstract function updateRule($modelId): array;

    /**
     * Fire on model update with success
     *
     * @param Model $model the new updated model
     */
    public function onAfterUpdate(Model $model)
    {

    }

    /**
     * Restore a soft deleted model
     *
     * @param Request $request
     *
     * @return array|JsonResponse
     * @throws \Exception
     */
    public function restore(Request $request)
    {
        try {
            $model = $this->getModel()->onlyTrashed()->where($this->key, $request->id)->first();
            if (empty($model)) {
                return self::liteResponse(config("lite-api-code.request.not_found"));
            }

            $model->restore();

            $this->onRestoreCompleted($model);

            return self::liteResponse(config("lite-api-code.request.success"));
        } catch (\Exception $exception) {
            if ($exception instanceof LiteResponseException) {
                return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
            } else {
                return self::liteResponse(config("lite-api-code.request.exception"), null, $exception->getMessage());
            }
        }
    }

    /**
     * Fire when the model has been restore in case to allow other action such as restoring his children
     *
     * @param Model $model the model restored
     */
    public function onRestoreCompleted(Model $model): void
    {
    }

    public function switchLang($locale): Application|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if (in_array($locale, Config::get('app.locales'))) {
            Session::put("locale", $locale);
        }
        return redirect(URL::previous());
    }

    /**
     * @throws Exception
     */
    protected function respondError($exception): JsonResponse|array
    {
        return self::liteResponse(config('lite-api-code.request.failure'), env("APP_ENV") == "local" ? $exception->getTrace() : null, $exception->getMessage());
    }
}
