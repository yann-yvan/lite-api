<?php

namespace Nycorp\LiteApi\Testing;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Guards a released API version against breaking changes.
 *
 * $this->assertApiContract($this->getJson('/api/v2.0/agents'), 'v2.0/agents.search');
 *
 * The first run records the shape in tests/Contracts/v2.0/agents.search.json (commit it).
 * Later runs fail when a field is removed or changes type; new fields are accepted.
 * Run with LITE_API_UPDATE_CONTRACTS=1 to re-record, only when starting a new version.
 */
trait AssertsApiContract
{
    protected function contractsPath(): string
    {
        return base_path('tests/Contracts');
    }

    public function assertApiContract(TestResponse|array $response, string $name): void
    {
        $json   = $response instanceof TestResponse ? $response->json() : $response;
        $actual = JsonContract::describe($json);
        $file   = $this->contractsPath() . "/$name.json";

        if (!is_file($file) || getenv('LITE_API_UPDATE_CONTRACTS')) {
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0755, true);
            }
            file_put_contents($file, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            Assert::assertFileExists($file);

            return;
        }

        $changes = JsonContract::breakingChanges(json_decode(file_get_contents($file), true), $actual);

        Assert::assertSame([], $changes, "API contract [$name] broken:\n - " . implode("\n - ", $changes));
    }
}
