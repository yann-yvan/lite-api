<?php

namespace Nycorp\LiteApi\Http\Controllers\Core;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Models\Log;

class LogLocalController extends CoreController
{

    protected array $searchColumns = [Log::MESSAGE, Log::CONTEXT, Log::EXTRA];

    public function __construct()
    {
        parent::__construct();
        $this->logChannel = ["daily"];
    }

    /**
     * @inheritDoc
     */
    function getModel(): Model
    {
        return new Log;
    }

    /**
     * @inheritDoc
     */
    function updateRule($modelId): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    function addRule(): array
    {
        return [
            Log::LEVEL_NAME => ["required", "max:100"],
            Log::MESSAGE => ["required"],
            Log::SERVICE => ["required", "max:100"],
            #Log::CONTEXT => ["required"],
            #Log::EXTRA => ["required"],
            Log::DATETIME => ["required", "date"],
        ];
    }

    /**
     *  * @OA\Post(
     *     path="/log/add",
     *   tags={"Log"},
     *   summary="Save log",
     *   description="Store all interaction in and between service",
     *   operationId="LogAdd",
     *   @OA\Parameter(
     *     name="service",
     *     required=true,
     *     in="query",
     *     description="The name of the calling service",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="level_name",
     *     required=true,
     *     in="query",
     *     description="The level of the log",
     *     @OA\Schema(
     *         type="string", enum={"INFO","WARNING","ERROR","EMERGENCY","DEBUG","CRITICAL","ALERT"}
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="message",
     *     required=true,
     *     in="query",
     *     description="The message",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="channel",
     *     required=true,
     *     in="query",
     *     description="The log channel",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="datetime",
     *     required=true,
     *     in="query",
     *     description="The timestamp when the log occur",
     *     @OA\Schema(
     *         type="datetime"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="context",
     *     required=true,
     *     in="query",
     *     description="The stacktrace of the log",
     *     @OA\Schema(
     *         type="json"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="extra",
     *     required=true,
     *     in="query",
     *     description="The stacktrace of the log",
     *     @OA\Schema(
     *         type="json"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     @OA\Schema(type="string"),
     *     )
     *
     * )
     * @param array $data
     * @return void
     */
    public function onBeforeAdd(array &$data): void
    {
       # \Illuminate\Support\Facades\Log::channel('daily')->{$data[Log::LEVEL_NAME]}($data[Log::MESSAGE], \request()->all());
    }


    /**
     *  * @OA\Get(
     *     path="/log/search",
     *   tags={"Log"},
     *   summary="Save log",
     *   description="Search all interaction in and between service",
     *   operationId="LogSearch",
     *   @OA\Parameter(
     *     name="service",
     *     required=false,
     *     in="query",
     *     description="The name of the calling service",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="level_name",
     *     required=false,
     *     in="query",
     *     description="The level of the log",
     *     @OA\Schema(
     *         type="string", enum={"INFO","WARNING","ERROR","EMERGENCY","DEBUG","CRITICAL","ALERT"}
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="message",
     *     required=false,
     *     in="query",
     *     description="The message",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="channel",
     *     required=false,
     *     in="query",
     *     description="The log channel",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="datetime",
     *     required=false,
     *     in="query",
     *     description="The timestamp when the log occur",
     *     @OA\Schema(
     *         type="datetime"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="context",
     *     required=false,
     *     in="query",
     *     description="The stacktrace of the log",
     *     @OA\Schema(
     *         type="json"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="extra",
     *     required=false,
     *     in="query",
     *     description="The stacktrace of the log",
     *     @OA\Schema(
     *         type="json"
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
     * @param mixed|null $id
     * @return JsonResponse
     * @throws Exception
     */
    public function search(Request $request, mixed $id = null): JsonResponse
    {
        return parent::search($request, $id);
    }

    public function create(array $data): Model
    {
        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->getModel()::create($data);
    }
}
