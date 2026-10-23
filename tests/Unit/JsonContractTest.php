<?php

use Nycorp\LiteApi\Testing\AssertsApiContract;
use Nycorp\LiteApi\Testing\JsonContract;
use PHPUnit\Framework\ExpectationFailedException;

function contractChanges(array $before, array $after): array
{
    return JsonContract::breakingChanges(JsonContract::describe($before), JsonContract::describe($after));
}

it('accepts additive changes', function () {
    expect(contractChanges(
        ['data' => ['id' => 1, 'tags' => [['name' => 'a']]]],
        ['data' => ['id' => 2, 'tags' => [['name' => 'b', 'color' => 'red']], 'extra' => true]],
    ))->toBe([]);
});

it('detects removed fields, type changes and unexpected nulls', function () {
    expect(contractChanges(
        ['data' => ['id' => 1, 'name' => 'a', 'tags' => [['name' => 'a']], 'owner' => 'x']],
        ['data' => ['id' => '1', 'tags' => [['label' => 'a']], 'owner' => null]],
    ))->toBe([
        '$.data.id changed type from number to string',
        '$.data.name was removed',
        '$.data.tags[].name was removed',
        '$.data.owner became null',
    ]);
});

it('treats empty arrays as compatible with objects and lists', function () {
    expect(contractChanges(['meta' => ['page' => 1], 'items' => [1]], ['meta' => [], 'items' => []]))->toBe([]);
});

it('records a contract then guards it', function () {
    $guard = new class {
        use AssertsApiContract;

        public string $path;

        protected function contractsPath(): string
        {
            return $this->path;
        }
    };
    $guard->path = sys_get_temp_dir() . '/lite-api-contracts-' . uniqid();

    $guard->assertApiContract(['data' => ['id' => 1]], 'v2.0/agents.search');
    expect("$guard->path/v2.0/agents.search.json")->toBeFile();

    $guard->assertApiContract(['data' => ['id' => 5, 'name' => 'new']], 'v2.0/agents.search');

    expect(fn() => $guard->assertApiContract(['data' => ['name' => 'no id']], 'v2.0/agents.search'))
        ->toThrow(ExpectationFailedException::class);

    $guard->assertApiContract(['data' => ['id' => 5]], 'v2.0/agents.search');
});
