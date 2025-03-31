<?php

namespace Nycorp\LiteApi\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Nycorp\LiteApi\Console\Commands\InstallMigration;
use Nycorp\LiteApi\Logging\LoggerService;

class LiteApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/lite-api-code.php', 'lite-api-code');
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../../config/lite-api-code.php' => config_path('lite-api-code.php'),
                __DIR__ . '/../../config/jwt.php' => config_path('jwt.php'),
                __DIR__ . '/../../config/l5-swagger.php' => config_path('l5-swagger.php'),
                __DIR__ . '/../../config/models.php' => config_path('models.php'),
                __DIR__ . '/../../config/laravel-migration-generator.php' => config_path('laravel-migration-generator.php'),
                __DIR__ . '/../../docs' =>  app_path('Http/Docs'),
            ], 'lite-api-config');

            $this->publishes([
                __DIR__ . '/../Http/Controllers/Auth' =>  app_path('Http/Controllers/Auth'),
            ], 'lite-api-auth');
        }

        $this->loadMigrationsFrom([
            __DIR__ . '/../../database/migrations',
        ]);

        $this->app->bind('lite-api:install', InstallMigration::class);

        $this->commands([
            'lite-api:install',
        ]);

        $this->app->make('config')->set('logging.channels.service_log', [
            'driver' => 'custom',
            'via' => LoggerService::class,
        ]);
    }
}
