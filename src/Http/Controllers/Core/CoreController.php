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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Notification\RegisterNotification;
use Nycorp\LiteApi\Response\DefResponse;
use Nycorp\LiteApi\Traits\ApiResponseTrait;

abstract class CoreController
{
    use ApiResponseTrait;

    public const ROUTE_ADD = 'add';

    public const ROUTE_UPDATE = 'update';

    public const ROUTE_DELETE = 'delete';

    public const ROUTE_RESTORE = 'restore';

    public const ROUTE_SEARCH = 'search';

    const ROOT_DIRECTORY = 'upload';

    public const ORDER_BY = "orderBy";

    public const ORDER_BY_DESC = "orderByDesc";

    protected array $excludedUpdateAttributes = [];

    protected int $pagination = 50;

    protected string $key = 'id';

    protected bool $rollbackOnAddNotificationFailed = false;

    protected bool $onAddNotify = false;

    protected bool $forceDelete = false;

    protected string $searchOrderKey = 'created_at';

    protected string $searchOrderBy = 'orderByDesc';
    protected array $searchColumns = [];

    protected mixed $logChannel;
    protected Request $request;


    public function __construct()
    {
        $this->logChannel = env('APP_DEBUG', false) ? env("LOG_CHANNEL") : 'daily';
    }

    /**
     * Use this only if the controller is not in a subdirectory of Controllers
     */
    public static function expose(string $prefix = '', array $exclude = [self::ROUTE_RESTORE], bool $softDelete = false, array $routes = []): void
    {
        Route::prefix($prefix)->group(function () use ($exclude, $softDelete, $routes) {
            $class = static::class;
            if (!in_array(self::ROUTE_ADD, $exclude, true)) {
                Route::post(self::ROUTE_ADD, "$class@add");
            }

            if (!in_array(self::ROUTE_UPDATE, $exclude, true)) {
                Route::put(self::ROUTE_UPDATE . '/{id}', "$class@update");
            }

            if (!in_array(self::ROUTE_SEARCH, $exclude, true)) {
                Route::get(self::ROUTE_SEARCH . '/{id?}', "$class@search");
            }

            if (!in_array(self::ROUTE_DELETE, $exclude, true)) {
                Route::delete(self::ROUTE_DELETE . '/{id}', "$class@delete");
            }

            if ($softDelete) {
                Route::patch(self::ROUTE_RESTORE . '/{id}', "$class@restore");
            }

            foreach ($routes as $route) {
                Route::{$route[0]}($route[1], [$class, $route[2]]);
            }
        });
    }

    public function delete(mixed $id): JsonResponse|array
    {
        try {
            Log::info("Delete: started {$this->stacktrace($id)} ");

            $model = $this->getModel()->where($this->key, $id)->first();

            if (empty($model)) {
                Log::debug("Delete: not found {$this->stacktrace($id)} ");
                return self::liteResponse(config('lite-api-code.request.not_found'));
            }

            $this->onBeforeDelete($model);

            $this->forceDelete ? $model->forceDelete() : $model->delete();

            $this->onAfterDelete($model);

            Log::info("Delete: deleted {$this->stacktrace( $id )} successfully " . ($this->forceDelete ? '[force]' : '[normal]'));
            return self::liteResponse(config('lite-api-code.request.success'), $model);
        } catch (LiteResponseException $exception) {
            Log::debug("Delete: cancelled {$this->stacktrace($id)} failed gracefully with: " . $exception->getMessage());
            return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
        } catch (\Exception $exception) {
            Log::error("Delete: error {$this->stacktrace($id)} failed with: " . $exception->getMessage());
            return self::liteResponse(config('lite-api-code.request.exception'), null, $exception->getMessage());
        }
    }

    protected function stacktrace($message = ''): string
    {
        $class = get_class($this->getModel());
        return "$message | in [**{$class}**] by {$this->getModel()->getTable()}";
    }

    /**
     * @return Model the model to manipulate
     */
    abstract public function getModel(): Model;

    /**
     * Fire before deleting the model
     *
     * @param Model $model the record that will be deleted
     */
    public function onBeforeDelete(Model $model): void
    {
    }

    /**
     * Fire after the model has been deleted
     */
    public function onAfterDelete(Model $model): void
    {
    }

    public static function sendSms($phone, $text)
    {
        return Http::get('http://51.195.252.172:13002/cgi-bin/sendsms',
            [
                'username' => '',
                'password' => '',
                'from' => env('APP_NAME'),
                'to' => $phone,
                'text' => $text,
            ]);
    }

    /**
     * Record a model according to his fillable and assets attributes
     *
     * @param Request $request the request query containing all parameter
     * @return array|JsonResponse
     *
     * @throws \Exception
     */
    public function add(Request $request)
    {
        $this->request = $request;
        Log::channel($this->logChannel)->debug("Add: {$this->stacktrace()} started ");

        $data = $request->all($this->getModel()->getFillable());

        if (array_key_exists('phone', $data)) {
            $data['phone'] = $this->cleanPhone($data['phone']);
        }

        $password = null;
        if (array_key_exists('password', $data) and empty($data['password'])) {
            $password = Str::random(8);
            $data['password'] = $password;
        } elseif (array_key_exists('password', $data)) {
            $password = $data['password'];
        }

        //make specific data transformation or custom validation
        try {
            $this->onBeforeAdd($data);
            $this->mutateBeforeAdd($data, $request);
        } catch (LiteResponseException $exception) {
            Log::channel($this->logChannel)->debug("Add: cancelled {$this->stacktrace()} failed gracefully with: " . $exception->getMessage());
            return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
        } catch (\Exception $exception) {
            Log::channel($this->logChannel)->error("Add: error {$this->stacktrace()} failed with: " . $exception->getMessage());
            return self::liteResponse(config('lite-api-code.request.exception'), null, $exception->getMessage());
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
                    if (array_key_exists('verified_at', $data) and empty($data['verified_at'])) {
                        Notification::send($this->getModel()->where($this->key, $response->getData()[$this->key])->first(), $this->getNotification($data, $password));
                    } else {
                        if (!empty($password)) {
                            //Send mail notification to newly created account if user has password
                            Notification::send($this->getModel()->where($this->key, $response->getData()[$this->key])->first(), $this->getNotification($data, $password));
                        }
                    }
                } catch (\Exception|\Throwable $exception) {
                    $this->rollbackAdd($response);
                    Log::channel($this->logChannel)->error("Add: cancelled {$this->stacktrace()} failed gracefully with: " . $exception->getMessage());
                    if ($exception instanceof LiteResponseException) {
                        return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
                    } else {
                        return self::liteResponse(config('lite-api-code.request.exception'), $exception->getTrace(), $exception->getMessage());
                    }
                }
            }
        } else {
            $this->rollbackAdd($response);
        }

        Log::channel($this->logChannel)->debug("Add: complete {$this->stacktrace()} successfully");

        return $response->getResponse();
    }

    public function cleanPhone($phone)
    {
        $phone = str_replace('+', '', $phone);
        //$phone = str_replace('237', '', $phone);

        return $phone;
    }

    /**
     * Fire before inserting the new record
     *
     * @param array $data the get from request without any change on field except for email and password
     * @deprecated
     */
    public function onBeforeAdd(array &$data): void
    {

    }

    /**
     * Fire before inserting the new record
     *
     * @param array $data the get from request without any change on field except for email and password
     */
    public function mutateBeforeAdd(array &$data, Request $request)
    {

    }

    public function storeFile($fileInputName, $directory = self::ROOT_DIRECTORY)
    {
        $file = request()->file($fileInputName);
        $mediaSet = [];
        if (is_array($file)) {
            foreach ($file as $key => $fileItem) {
                $mediaSet[] = $this->saveMedia($fileItem, $directory, $key);
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

        $path = implode('/', [self::ROOT_DIRECTORY, Str::replaceLast('/', '', $directory)]);
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
        return strtolower(get_class($this->getModel()));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws Exception
     */
    public function save(array $data): JsonResponse
    {
        if (array_key_exists('uuid', $data)) {
            $data['uuid'] = Str::uuid();
        }

        $validator = $this->validator($data);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $message = $errors->first();
            return self::liteResponse(config('lite-api-code.request.validation_error'), $errors, $message);
        }

        try {
            $model = $this->create($data);
            $this->saved($model);
            $response = new DefResponse(self::liteResponse(config('lite-api-code.request.success'), $model));

            return $response->getResponse();
        } catch (LiteResponseException $exception) {
            Log::debug("Add: {$this->stacktrace()} failed gracefully with: " . $exception->getMessage());
            return self::liteResponse($exception->getCode(), array_merge($exception->getData() ?? [], $model->toArray()), $exception->getMessage());
        } catch (\Exception $exception) {
            Log::error("Add: {$this->stacktrace()} failed gracefully with: " . $exception->getMessage());
            return self::liteResponse(config('lite-api-code.request.exception'), $exception->getTrace(), $exception->getMessage());
        }
    }

    /**
     * Default validator in case of non specification
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
    abstract public function addRule(): array;

    /**
     * Record the new model
     *
     * @param array $data all required fields that should be record
     */
    public function create(array $data): Model
    {
        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
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
     * @return RegisterNotification the mailable notification to send to the new created model
     */
    public function getNotification(array $data, string $password): RegisterNotification
    {
        return new RegisterNotification('Place email here or any login', 'Place password here');
    }

    /**
     * Delete saved model
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
                Log::error("rollbackAdd: {$this->stacktrace()} failed with: " . $ex->getMessage());
            }
        }
    }

    /**
     * Search account by given available input
     *
     *
     * @param mixed $id the specific of the record to return
     *
     * @throws Exception
     */
    public function search(Request $request, mixed $id = null): JsonResponse
    {
        $query = $this->getModel()::query();

        // return single record when specified
        if ($id) {
            $model = $query->find($id);
            return self::liteResponse($model ? config('lite-api-code.request.success') : config('lite-api-code.request.not_found'), $model);
        }

        $entireText = $request->boolean('inclusive', true);
        $this->defaultSearchCriteria($query, $request, $entireText);
        Log::debug("Search: {$this->stacktrace()}", $request->all());

        $this->mutateSearchQuery($query, $request);

        if ($request->has('perPage') and is_int($request->perPage)) {
            $this->pagination = $request->perPage;
        }

        return self::liteResponse(config('lite-api-code.request.success'), $query->{$this->searchOrderBy}($this->searchOrderKey)->paginate($this->pagination));
    }

    /**
     * Add default field attribute search criteria
     */
    public function defaultSearchCriteria($query, $request, bool $entireText = true, bool $entireTextOnly = false): void
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

        $query->search(implode(' ', $keywords), null, $entireText, $entireTextOnly);

        if (!empty($constraints)) {
            $query->where($constraints);
        }
    }

    /**
     * Add some specific search criteria
     */
    public function mutateSearchQuery($query, $request): void
    {
    }

    /**
     * Update any model according to his available field in the fillable and assets attributes
     *
     *
     *
     * @throws Exception
     */
    public function update(Request $request, $id)
    {
        Log::info("Updating $id");
        // get only set model fillable attributes
        $data = $request->only($this->getModel()->getFillable());

        // Clean phone
        if (array_key_exists('phone', $data)) {
            $data['phone'] = $this->cleanPhone($data['phone']);
        }

        // save all files
        foreach ($this->getModel()->getAssets() as $asset) {
            if (in_array($asset, $data)) {
                $data[$asset] = $this->storeFile($asset, $this->getFileDirectory());
            }
        }

        // remove empty value
        if (empty($data)) {
            return self::liteResponse(config('lite-api-code.request.validation_error'), null, 'Empty data set, no value to update');
        }

        // verify that the specified id exists
        $model = $this->getModel()->where($this->key, $id)->first();
        if (empty($model)) {
            Log::debug("Update: {$this->stacktrace()} not found");
            return self::liteResponse(config('lite-api-code.request.not_found'));
        }

        $this->onBeforeUpdateWithModel($data, $model);

        // This part remove attribute that should not be updated
        if ($this->excludedUpdateAttributes and !empty($this->excludedUpdateAttributes)) {
            foreach ($this->excludedUpdateAttributes as $excludedUpdateAttribute) {
                if (array_key_exists($excludedUpdateAttribute, $data)) {
                    unset($data[$excludedUpdateAttribute]);
                }
            }
        }

        // validation using the updateRule
        $validator = Validator::make($data, $this->updateRule($model->id));
        if ($validator->fails()) {
            return self::liteResponse(config('lite-api-code.request.validation_error'), $validator->errors());
        }

        try {
            // Set updated field
            foreach ($data as $key => $datum) {
                $model->{$key} = $datum;
            }

            // Updating model
            $model->update();

            // Refresh model and return it with update
            $updatedModel = $model->refresh();

            $this->onAfterUpdate($updatedModel);

            return self::liteResponse(config('lite-api-code.request.success'), $updatedModel);
        } catch (LiteResponseException $exception) {
            Log::debug("Update: {$this->stacktrace()}  {$exception->getMessage()}");
            return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
        } catch (\Exception $exception) {
            Log::error("Update: {$this->stacktrace()} {$exception->getMessage()}");
            return self::liteResponse(config('lite-api-code.request.exception'), null, $exception->getMessage());
        }
    }

    /**
     * Fire before updating an existing record
     *
     * @param array $data the get from request without any change on field
     */
    public function onBeforeUpdateWithModel(array &$data, Model $model): void
    {

    }

    /**
     * Validation for model update
     *
     * @param mixed $modelId of the model about to be updated
     * @return array and array of validation
     */
    abstract public function updateRule(mixed $modelId): array;

    /**
     * Fire on model update with success
     *
     * @param Model $model the new updated model
     */
    public function onAfterUpdate(Model $model): void
    {

    }

    /**
     * Restore a soft deleted model
     *
     *
     *
     * @throws Exception
     */
    public function restore(mixed $id): JsonResponse
    {
        try {
            Log::info('Restoring model ' . $id);
            $model = $this->getModel()->onlyTrashed()->where($this->key, $id)->first();

            if (empty($model)) {
                Log::debug("Model {$id} not found");
                return self::liteResponse(config('lite-api-code.request.not_found'));
            }

            $model->restore();

            $this->onRestoreCompleted($model);
            Log::info("Model {$id} restored");
            return self::liteResponse(config('lite-api-code.request.success'), $model);
        } catch (LiteResponseException $exception) {
            Log::debug("Restoring model {$id} failed gracefully");
            return self::liteResponse($exception->getCode(), $exception->getData(), $exception->getMessage());
        } catch (\Exception $exception) {
            Log::error("Restoring model {$id} failed: " . $exception->getMessage());
            return self::liteResponse(config('lite-api-code.request.exception'), null, $exception->getMessage());
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
            Session::put('locale', $locale);
        }

        return redirect(URL::previous());
    }

    /**
     * @throws Exception
     */
    protected function respondError($exception): JsonResponse|array
    {
        return self::liteResponse(config('lite-api-code.request.failure'), env('APP_ENV') == 'local' ? $exception->getTrace() : null, $exception->getMessage());
    }
}
