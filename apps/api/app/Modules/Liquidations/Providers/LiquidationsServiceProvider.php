<?php

namespace App\Modules\Liquidations\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Modules\Reimbursements\Http\Middleware\AuthenticateAiServiceApi;

class LiquidationsServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        // Alias for AI service callback — ensure available even if Reimbursements provider hasn't booted yet
        app('router')->aliasMiddleware('auth.ai-service-api', AuthenticateAiServiceApi::class);

        // Load Migrations
        $migrationPath = __DIR__ . '/../Database/Migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        // Load Routes
        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::middleware('api')
                ->prefix('api/liquidations')
                ->group($routeFile);
        }
    }
}
