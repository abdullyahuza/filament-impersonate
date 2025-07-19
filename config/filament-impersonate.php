<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Redirect After Impersonation
    |--------------------------------------------------------------------------
    |
    | The named route to redirect the user to after starting or stopping
    | impersonation.
    |
    */
    'redirect_after_impersonation' => 'filament.app.pages.dashboard',

    /*
    |--------------------------------------------------------------------------
    | Enable Role/Permission Tabs
    |--------------------------------------------------------------------------
    |
    | Toggle visibility of tabs for role or permission-based impersonation.
    | Set to true when ready to use these features.
    |
    */
    'enable_role_tab' => false, // Set to true to enable role-based impersonation
    'enable_permission_tab' => false, // Set to true to enable permission-based impersonation

    /*
    |--------------------------------------------------------------------------
    | Impersonation Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard used during impersonation.
    |
    */
    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model class used for impersonation.
    |
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Authorization to Impersonate
    |--------------------------------------------------------------------------
    |
    | Control who is allowed to start impersonating other users.
    | You can restrict by role, permission, user ID, etc.
    |
    */
    'can_impersonate_resolver' => fn ($user) => $user?->hasRole('Super Admin') || $user?->can('impersonate_users'),


    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | The session key used to store the impersonator's ID.
    |
    */
    'session_key' => 'filament_impersonator_id',

    /*
    |--------------------------------------------------------------------------
    | Display Name Resolver
    |--------------------------------------------------------------------------
    |
    | A closure to determine how to display the impersonated user's name.
    |
    */
    'display_name_resolver' => fn ($user) => $user->name ?? $user->full_name ?? $user->first_name ?? 'Unknown User',

    /*
    |--------------------------------------------------------------------------
    | Exclude These User IDs from Being Impersonated
    |--------------------------------------------------------------------------
    |
    | Add user IDs here that should never be impersonated (e.g., super admins).
    |
    */
    'excluded_user_ids' => [
        // e.g. 1, 2, 3
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | Restrict impersonation to specific environments.
    | Uses Laravel's APP_ENV.
    |
    */
    'allowed_environments' => ['local', 'staging', 'production'],

    /*
    |--------------------------------------------------------------------------
    | Action UI Configuration
    |--------------------------------------------------------------------------
    |
    | Customize how the impersonation action looks in Filament.
    |
    */
    'action_ui' => [
        'icon' => 'heroicon-o-user',
        'icon_position' => 'after', // 'before' or 'after'
        'size' => 'sm',
        'modal_icon' => 'heroicon-o-user',
        'modal_width' => 'xl', // Options: sm, md, lg, xl, 2xl, ..., 7xl
    ],
];
