<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\TextGeneration\TextGenerationProviderInterface;
use App\Services\TextGeneration\GeminiTextProvider;
use App\Services\ImageGeneration\ImageGenerationProviderInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TextGenerationProviderInterface::class, function ($app) {
            $provider = config('services.ai.text_provider', 'gemini');
            
            if ($provider === 'gemini') {
                return new GeminiTextProvider();
            }
            
            // Fallback default
            return new GeminiTextProvider();
        });

        $this->app->bind(ImageGenerationProviderInterface::class, function ($app) {
            $provider = config('services.ai.image_provider', 'pollinations');
            
            if ($provider === 'pollinations') {
                return new \App\Services\ImageGeneration\PollinationsImageProvider();
            }
            
            // Fallback default
            return new \App\Services\ImageGeneration\PollinationsImageProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
