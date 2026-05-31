<?php

declare(strict_types=1);

namespace ArgusApi;

use ArgusApi\Authorization\AuthorizationServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ArgusApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/argus-api.php', 'argus-api');

        $this->app->register(AuthorizationServiceProvider::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/argus-api.php' => $this->app->configPath('argus-api.php'),
        ], 'argus-api-config');

        $this->publishes([
            __DIR__.'/../stubs/argus-api-authorization-provider.stub' => $this->app->basePath('app/Providers/ArgusApiAuthorizationServiceProvider.php'),
        ], 'argus-api-authorization');

        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        Route::group([
            'prefix' => config('argus-api.prefix', 'argus-api'),
            'middleware' => config('argus-api.middleware', ['auth:sanctum']),
        ], function (): void {
            require __DIR__.'/../routes/argus-api.php';
        });
    }
}
