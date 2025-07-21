<?php

namespace Abdullyahuza\FilamentImpersonate;

use Filament\Panel;
use Livewire\Livewire;
use Filament\Contracts\Plugin;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Abdullyahuza\FilamentImpersonate\Livewire\ImpersonateModalButton;

class FilamentImpersonate implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $middleware = $panel->getMiddleware();

        $toRemove = [
            \Filament\Http\Middleware\AuthenticateSession::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
        ];

        $middleware = array_values(array_filter(
            $middleware,
            fn ($item) => !in_array($item, $toRemove, true)
        ));

        $impersonateMiddleware = class_exists(\Filament\Http\Middleware\AuthenticateSession::class)
            ? \Abdullyahuza\FilamentImpersonate\Middleware\ImpersonateSessionMiddleware::class
            : \Abdullyahuza\FilamentImpersonate\Middleware\LegacyImpersonateSessionMiddleware::class;

        $startSessionIndex = array_search(\Illuminate\Session\Middleware\StartSession::class, $middleware);

        if ($startSessionIndex !== false) {
            array_splice($middleware, $startSessionIndex + 1, 0, [$impersonateMiddleware]);
        } else {
            $middleware[] = $impersonateMiddleware;
        }
        $middleware = array_values(array_unique($middleware));

        $panel->middleware($middleware);

        $panel
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render('@livewire("impersonate-modal-button")'),
            );
    }

    public function boot(Panel $panel): void
    {
        Livewire::component('impersonate-modal-button', ImpersonateModalButton::class);
    }

    public function getId(): string
    {
        return 'filament-impersonate';
    }
}
