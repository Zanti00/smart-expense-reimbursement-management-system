<?php

namespace App\Modules\CashAdvances\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Policies\CashAdvancePolicy;

class CashAdvancesServiceProvider extends ServiceProvider
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
        // Register Policy
        Gate::policy(CashAdvance::class, CashAdvancePolicy::class);

        // Load Migrations
        $migrationPath = __DIR__ . '/../Database/Migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        // Load Routes
        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::middleware('api')
                ->prefix('api/cash-advances')
                ->group($routeFile);
        }
    }
}
