<?php

namespace Nycorp\LiteApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Http\Controllers\Core\CoreController;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Models\ResponseCode;

/**
 * REST resource scoped to the actor owner, public ids "WID-{id}", stable representation, business refusals
 */
class WidgetController extends CoreController
{
    protected string $createStrategy = self::CREATE_STRATEGY_CREATE_ONLY;

    public function getModel(): Model
    {
        return new Widget();
    }

    protected function baseQuery(): Builder
    {
        return Widget::query()->where('company_id', LiteApi::actor()->owner->getKey());
    }

    protected function findRecord(Builder $query, mixed $id): ?Model
    {
        return str_starts_with((string)$id, 'WID-') ? $query->whereKey(substr($id, 4))->first() : null;
    }

    protected function present(Model $model): mixed
    {
        return ['id' => 'WID-' . $model->getKey(), 'name' => $model->name];
    }

    protected function dataForAdd(Request $request): array
    {
        return $request->only('name') + ['company_id' => LiteApi::actor()->owner->getKey()];
    }

    public function create(array $data): Model
    {
        if ($data['name'] === 'forbidden') {
            throw new LiteResponseException(ResponseCode::REQUEST_NOT_AUTHORIZED, 'Name refused');
        }

        return parent::create($data);
    }

    public function onBeforeUpdateWithModel(array &$data, Model $model): void
    {
        if (($data['name'] ?? null) === 'forbidden') {
            throw new LiteResponseException(ResponseCode::REQUEST_NOT_AUTHORIZED, 'Rename refused');
        }
    }

    protected function persistDelete(Model $model): void
    {
        if ($model->name === 'locked') {
            throw new LiteResponseException(ResponseCode::REQUEST_NOT_AUTHORIZED, 'Locked widget');
        }

        parent::persistDelete($model);
    }

    public function onBeforeRestore(Model $model): void
    {
        if ($model->name === 'gone') {
            throw new LiteResponseException(ResponseCode::REQUEST_NOT_AUTHORIZED, 'Cannot restore');
        }
    }

    public function stats(): JsonResponse
    {
        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, ['count' => $this->baseQuery()->count()]);
    }

    public function addRule(): array
    {
        return ['name' => 'required|string|max:50'];
    }

    public function updateRule(mixed $modelId): array
    {
        return ['name' => 'sometimes|string|max:50'];
    }
}
