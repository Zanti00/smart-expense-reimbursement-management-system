<?php

namespace App\Modules\Shared\Providers;

use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    /**
     * Register any shared services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any shared services.
     */
    public function boot(\Illuminate\Routing\Router $router): void
    {
        // Register the external auth middleware alias
        $router->aliasMiddleware('auth.external', \App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class);

        // Load Shared module routes
        $routesPath = __DIR__ . '/../routes/api.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
