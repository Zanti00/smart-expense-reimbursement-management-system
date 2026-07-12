<?php

namespace App\Modules\Reimbursements\Providers;

use App\Modules\Reimbursements\Http\Middleware\AuthenticatePrsReimbursementApi;
use App\Modules\Reimbursements\Http\Middleware\AuthenticateAiServiceApi;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class ReimbursementsServiceProvider extends ServiceProvider
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
        app('router')->aliasMiddleware('auth.prs-reimbursement-api', AuthenticatePrsReimbursementApi::class);
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
                ->prefix('api/reimbursements')
                ->group($routeFile);
        }
    }
}
