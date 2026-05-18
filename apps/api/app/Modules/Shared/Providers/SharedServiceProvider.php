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
    public function boot(): void
    {
        // Register the external auth middleware alias
        $this->app['router']->aliasMiddleware('auth.external', \App\Modules\Shared\Http\Middleware\AuthenticateWithExternalService::class);
    }
}
