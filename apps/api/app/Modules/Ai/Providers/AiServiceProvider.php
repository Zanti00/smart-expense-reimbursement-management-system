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
        $this->app->singleton(
            \App\Modules\Ai\Contracts\OcrEngineInterface::class,
            \App\Modules\Ai\Services\TesseractOcrEngine::class
        );
    }

    /**
     * Bootstrap Ai/OCR services.
     */
    public function boot(): void
    {
        // Load module migrations or routing when implemented.
    }
}
