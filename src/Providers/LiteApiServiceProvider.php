<?php

namespace Nycorp\LiteApi\Providers;

use Illuminate\Support\ServiceProvider;
use Nycorp\LiteApi\Console\Commands\InstallMigration;
use Nycorp\LiteApi\Logging\LoggerService;

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
            __DIR__.'/../../database/migrations',
        ]);

        $this->app->bind('lite-api:install', InstallMigration::class);

        $this->commands([
            'lite-api:install',
        ]);

        $this->app->make('config')->set('logging.channels.service_log', [
            'driver' => 'custom',
            'via' => LoggerService::class
        ]);
    }
}
