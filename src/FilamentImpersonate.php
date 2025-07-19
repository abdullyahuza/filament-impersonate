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
