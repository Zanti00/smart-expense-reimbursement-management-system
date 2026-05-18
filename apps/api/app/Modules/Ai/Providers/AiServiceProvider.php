<?php

namespace App\Modules\Ai\Providers;

use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register Ai/OCR service bindings.
     */
    public function register(): void
    {
        // Bindings for OcrEngineInterface will be registered here.
    }

    /**
     * Bootstrap Ai/OCR services.
     */
    public function boot(): void
    {
        // Load module migrations or routing when implemented.
    }
}
