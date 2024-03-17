<?php

use Nycorp\LiteApi\Providers\LiteApiServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LiteApiServiceProvider::class,
        ];
    }
}
