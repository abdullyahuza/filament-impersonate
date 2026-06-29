<?php

namespace Abdullyahuza\FilamentImpersonate\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ImpersonateModalButton extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function render()
    {
        return view('filament-impersonate::livewire.impersonate-modal-button');
    }

    public function SwitchAction(): Action
    {
        $impersonatorId = session(config('filament-impersonate.session_key', 'filament_impersonator_id'));
        $originalUser   = isImpersonating() ? ($this->getUserModel())::withoutGlobalScopes()->find($impersonatorId) : null;
        $userName       = filamentImpersonateDisplayName(auth()->user());
        $roleNames      = auth()->user()->getRoleNames()->implode(', ');

        if (isImpersonating() && $originalUser) {
            return Action::make('Switch')
                ->label(fn () => "You're impersonating {$userName} ({$roleNames}), click to stop impersonation")
                ->color('info')
                ->icon('heroicon-o-user-minus')
                ->visible(filamentImpersonateEnabled())
                ->action(fn () => $this->stopImpersonation());
        }

        return Action::make('Switch')
            ->label('Impersonate a user')
            ->modalHeading('Filament Impersonate')
            ->modalSubmitActionLabel('Submit')
            ->modalIcon('heroicon-o-user')
            ->modalDescription('Select a user to impersonate.')
            ->icon(filamentImpersonateUi('icon', 'heroicon-o-user'))
            ->iconPosition(
                IconPosition::tryFrom(filamentImpersonateUi('icon_position', 'after')) ?? IconPosition::After
            )
            ->modalWidth(
                MaxWidth::tryFrom(filamentImpersonateUi('modal_width', 'lg')) ?? MaxWidth::Large
            )
            ->size(
                ActionSize::tryFrom(filamentImpersonateUi('size', 'sm')) ?? ActionSize::Small
            )
            ->modalIcon(filamentImpersonateUi('modal_icon', 'heroicon-o-user'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modal()
            ->visible(filamentImpersonateEnabled())
            ->form([
                Tabs::make('Impersonation Type')->tabs([

                    // ── Tab 1: Pick a specific user ───────────────────────────
                    Tab::make('User')->icon('heroicon-m-user')->schema([
                        Select::make('user_id')
                            ->label('Select User')
                            ->options(function () {
                                $excluded = config('filament-impersonate.excluded_user_ids', []);
                                if (is_callable($excluded)) {
                                    $excluded = $excluded();
                                }

                                // withoutGlobalScopes() bypasses any tenant/school scoping
                                return ($this->getUserModel())::withoutGlobalScopes()
                                    ->where('id', '!=', auth()->id())
                                    ->whereNotIn('id', $excluded)
                                    ->whereNull('deleted_at')
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => filamentImpersonateDisplayName($user)]);
                            })
                            ->searchable(),
                    ]),

                    // ── Tab 2: Pick a role → then pick a user with that role ──
                    Tab::make('Role')->icon('heroicon-m-shield-check')->schema([
                        Select::make('role_id')
                            ->label('Select Role')
                            ->options(function () {
                                // Spatie respects setPermissionsTeamId() automatically,
                                // so this is tenant-scoped when in a school panel
                                return Role::orderBy('name')->pluck('name', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->placeholder('Choose a role…'),

                        Select::make('user_id_for_role')
                            ->label('Select User')
                            ->placeholder('Choose a user with this role…')
                            ->visible(fn (Get $get) => ! empty($get('role_id')))
                            ->options(function (Get $get) {
                                $roleId = $get('role_id');
                                if (! $roleId) {
                                    return [];
                                }

                                $excluded = config('filament-impersonate.excluded_user_ids', []);
                                if (is_callable($excluded)) {
                                    $excluded = $excluded();
                                }

                                return ($this->getUserModel())::withoutGlobalScopes()
                                    ->where('id', '!=', auth()->id())
                                    ->whereNotIn('id', $excluded)
                                    ->whereNull('deleted_at')
                                    ->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => filamentImpersonateDisplayName($user)]);
                            })
                            ->searchable(),
                    ])->visible(config('filament-impersonate.enable_role_tab', false)),

                    // ── Tab 3: Pick by permission ─────────────────────────────
                    Tab::make('Permission')->icon('heroicon-m-lock-open')->schema([
                        Select::make('permission_id')
                            ->label('Select Permission')
                            ->options(Permission::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->placeholder('Choose a permission…'),

                        Select::make('user_id_for_permission')
                            ->label('Select User')
                            ->placeholder('Choose a user with this permission…')
                            ->visible(fn (Get $get) => ! empty($get('permission_id')))
                            ->options(function (Get $get) {
                                $permId = $get('permission_id');
                                if (! $permId) {
                                    return [];
                                }

                                $excluded = config('filament-impersonate.excluded_user_ids', []);
                                if (is_callable($excluded)) {
                                    $excluded = $excluded();
                                }

                                return ($this->getUserModel())::withoutGlobalScopes()
                                    ->where('id', '!=', auth()->id())
                                    ->whereNotIn('id', $excluded)
                                    ->whereNull('deleted_at')
                                    ->whereHas('permissions', fn ($q) => $q->where('permissions.id', $permId))
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => filamentImpersonateDisplayName($user)]);
                            })
                            ->searchable(),
                    ])->visible(config('filament-impersonate.enable_permission_tab', false)),

                ]),
            ])
            ->action(fn (array $data) => $this->handleImpersonation($data));
    }

    protected function handleImpersonation(array $data): void
    {
        $user = $this->findTargetUser($data);

        if ($user) {
            $this->impersonate($user);
        }
    }

    protected function findTargetUser(array $data): ?Model
    {
        // Direct user selection
        if (! empty($data['user_id'])) {
            return ($this->getUserModel())::withoutGlobalScopes()->find($data['user_id']);
        }

        // Role tab: user was explicitly chosen after filtering by role
        if (! empty($data['user_id_for_role'])) {
            return ($this->getUserModel())::withoutGlobalScopes()->find($data['user_id_for_role']);
        }

        // Permission tab: user was explicitly chosen after filtering by permission
        if (! empty($data['user_id_for_permission'])) {
            return ($this->getUserModel())::withoutGlobalScopes()->find($data['user_id_for_permission']);
        }

        return null;
    }

    protected function impersonate(Model $targetUser): void
    {
        $currentTenant = \Filament\Facades\Filament::getTenant();

        // Auth::login() writes the new user to the session so the next request
        // (the redirect) authenticates as the impersonated user.
        // Auth::loginUsingId() only switches the user in memory for the current
        // request and does NOT update the session, so the redirect would still
        // see the original user and abort(404) on canAccessTenant() checks.
        Auth::login($targetUser);

        $effectiveTenant = $this->resolveEffectiveTenant($targetUser, $currentTenant);
        $url             = $this->resolveRedirect($targetUser, $effectiveTenant);

        // navigate: false forces a full HTTP request so the entire middleware
        // chain (IdentifyTenant, SyncShieldTenant, etc.) re-runs correctly.
        $this->redirect($url, navigate: false);
    }

    protected function stopImpersonation(): void
    {
        $originalId   = session(config('filament-impersonate.session_key', 'filament_impersonator_id'));
        $originalUser = $originalId
            ? ($this->getUserModel())::withoutGlobalScopes()->find($originalId)
            : null;

        if ($originalUser) {
            Auth::login($originalUser);
        } else {
            Auth::logout();
        }

        $currentTenant   = \Filament\Facades\Filament::getTenant();
        $redirectUser    = $originalUser ?? auth()->user();
        $effectiveTenant = $this->resolveEffectiveTenant($redirectUser, $currentTenant);
        $url             = $this->resolveRedirect($redirectUser, $effectiveTenant);

        $this->redirect($url, navigate: false);
    }

    /**
     * Determines the best tenant to redirect to after a user switch.
     *
     * If $user can access the current tenant, keep it (same-school case).
     * Otherwise fall back to the first tenant the user actually belongs to.
     *
     * NOTE: we intentionally avoid using getTenants() / BelongsToMany here because
     * the pivot table may have its own `id` column that overwrites the related
     * model's primary key during hydration, producing an empty route key and a 404.
     * Instead we query the tenants() relationship as a fresh Eloquent query so only
     * the related model's own columns are returned.
     */
    protected function resolveEffectiveTenant(?Model $user, $currentTenant)
    {
        if (! $currentTenant || ! $user) {
            return $currentTenant;
        }

        // User already has access to the current tenant — keep it
        if (method_exists($user, 'canAccessTenant') && $user->canAccessTenant($currentTenant)) {
            return $currentTenant;
        }

        // User doesn't belong to the current tenant — find their first available tenant.
        // Try each common relationship name; use ->getRelated()->newModelQuery() so we
        // bypass the pivot's id-column conflict by selecting only the related table.
        foreach (['schools', 'tenants', 'organizations', 'teams', 'companies'] as $relation) {
            if (! method_exists($user, $relation)) {
                continue;
            }

            try {
                $relation   = $user->$relation();
                $relatedTbl = $relation->getRelated()->getTable();

                // Select ONLY the related table's columns to avoid pivot id conflicts
                $firstTenant = $relation
                    ->select("{$relatedTbl}.*")
                    ->first();

                if ($firstTenant && $firstTenant->getKey()) {
                    // Reload via a direct find so the model is fully, cleanly hydrated
                    $clean = $firstTenant->newModelQuery()->find($firstTenant->getKey());
                    if ($clean) {
                        return $clean;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $currentTenant; // last-resort fallback
    }

    protected function resolveRedirect(?Model $user, $tenant): string
    {
        $config = config('filament-impersonate.redirect_after_impersonation');

        if (is_callable($config)) {
            return $config($user, $tenant);
        }

        try {
            return route($config);
        } catch (\Exception) {
            return $config;
        }
    }

    protected function getUserModel(): string
    {
        return filamentImpersonateUserModel();
    }
}
