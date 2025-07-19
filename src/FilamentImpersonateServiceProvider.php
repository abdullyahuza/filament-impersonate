<?php

namespace Abdullyahuza\FilamentImpersonate;

use Illuminate\Support\ServiceProvider;

class FilamentImpersonateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-impersonate.php', 'filament-impersonate');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filament-impersonate.php' => config_path('filament-impersonate.php'),
        ], 'filament-impersonate-config');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-impersonate');

        // Load helpers
        if (file_exists(__DIR__ . '/Support/helpers.php')) {
            require_once __DIR__ . '/Support/helpers.php';
        }
    }

}
