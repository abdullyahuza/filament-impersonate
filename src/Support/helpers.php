<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('isImpersonating')) {
    function isImpersonating(): bool
    {
        return session()->has(config('filament-impersonate.session_key', 'filament_impersonator_id')) && Auth::id() !== session(config('filament-impersonate.session_key', 'filament_impersonator_id'));
    }
}

if (!function_exists('filamentImpersonateUi')) {
    function filamentImpersonateUi(string $key, $default = null)
    {
        return config("filament-impersonate.action_ui.$key", $default);
    }
}


if (!function_exists('filamentImpersonateUserModel')) {
    function filamentImpersonateUserModel(): string
    {
        return config('filament-impersonate.user_model', \Illuminate\Foundation\Auth\User::class);
    }
}

if (!function_exists('filamentImpersonateDisplayName')) {
    function filamentImpersonateDisplayName($user): string
    {
        $resolver = config('filament-impersonate.display_name_resolver');

        return is_callable($resolver)
            ? call_user_func($resolver, $user)
            : ($user->name ?? 'Unknown User');
    }
}

if (!function_exists('filamentImpersonateEnabled')) {
    function filamentImpersonateEnabled(): bool
    {
        $allowedEnvironment = in_array(app()->environment(), config('filament-impersonate.allowed_environments', []));

        // Always allow if impersonating, so the "stop" button shows
        if (isImpersonating()) {
            return $allowedEnvironment;
        }

        $user = auth()->user();
        $canImpersonateResolver = config('filament-impersonate.can_impersonate_resolver');

        $canImpersonate = is_callable($canImpersonateResolver)
            ? $canImpersonateResolver($user)
            : true;

        return $allowedEnvironment && $canImpersonate;
    }
}

