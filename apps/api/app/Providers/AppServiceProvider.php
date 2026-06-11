<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Modules\Reimbursements\Models\Receipt::observe(\App\Modules\Reimbursements\Observers\ReceiptStatusObserver::class);
    }
}
