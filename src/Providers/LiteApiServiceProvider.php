<?php

namespace Nycorp\LiteApi\Providers;

use Illuminate\Support\ServiceProvider;

class LiteApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/lite-api-code.php', 'lite-api-code');

    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/lite-api-code.php' => config_path('lite-api-code.php'),
        ]);

        $this->loadMigrationsFrom([
            __DIR__.'/../../migrations',
        ]);
    }
}
