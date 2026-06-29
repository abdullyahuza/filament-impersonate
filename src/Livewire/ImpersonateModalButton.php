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
        $originalUser   = isImpersonating() ? ($this->getUserModel())::find($impersonatorId) : null;
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
                    Tab::make('User')->icon('heroicon-m-user')->schema([
                        Select::make('user_id')
                            ->label('Select User')
                            ->options(function () {
                                // Resolve excluded_user_ids — supports both array and callable
                                $excluded = config('filament-impersonate.excluded_user_ids', []);
                                if (is_callable($excluded)) {
                                    $excluded = $excluded();
                                }

                                // withoutGlobalScopes() bypasses any tenant/school scoping
                                // on the user model so all users in the system are listed
                                return ($this->getUserModel())::withoutGlobalScopes()
                                    ->where('id', '!=', auth()->id())
                                    ->whereNotIn('id', $excluded)
                                    ->whereNull('deleted_at')
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => filamentImpersonateDisplayName($user)]);
                            })
                            ->searchable(),
                    ]),
                    Tab::make('Role')->icon('heroicon-m-shield-check')->schema([
                        Select::make('role_ids')
                            ->label('Select Role')
                            ->options(Role::pluck('name', 'id'))
                            ->searchable()
                            ->multiple(),
                    ])->visible(false),
                    Tab::make('Permission')->icon('heroicon-m-lock-open')->schema([
                        Select::make('permission_ids')
                            ->label('Select Permission')
                            ->options(Permission::pluck('name', 'id'))
                            ->multiple()
                            ->searchable(),
                    ])->visible(false),
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
        if (! empty($data['user_id'])) {
            return ($this->getUserModel())::withoutGlobalScopes()->find($data['user_id']);
        }

        if (! empty($data['role_ids'])) {
            return ($this->getUserModel())::withoutGlobalScopes()
                ->where('id', '!=', auth()->id())
                ->whereHas('roles', fn ($q) => $q->whereIn('id', $data['role_ids']))
                ->withCount(['roles as matching_roles_count' => fn ($q) => $q->whereIn('id', $data['role_ids'])])
                ->having('matching_roles_count', count($data['role_ids']))
                ->first();
        }

        if (! empty($data['permission_ids'])) {
            return ($this->getUserModel())::withoutGlobalScopes()
                ->where('id', '!=', auth()->id())
                ->whereHas('permissions', fn ($q) => $q->whereIn('id', $data['permission_ids']))
                ->withCount(['permissions as matching_permissions_count' => fn ($q) => $q->whereIn('id', $data['permission_ids'])])
                ->having('matching_permissions_count', count($data['permission_ids']))
                ->first();
        }

        return null;
    }

    protected function impersonate(Model $targetUser): mixed
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        Auth::loginUsingId($targetUser->id);
        return redirect($this->resolveRedirect($targetUser, $tenant));
    }

    protected function stopImpersonation(): mixed
    {
        $originalId   = session(config('filament-impersonate.session_key', 'filament_impersonator_id'));
        $originalUser = $originalId
            ? ($this->getUserModel())::withoutGlobalScopes()->find($originalId)
            : null;

        if ($originalUser) {
            Auth::loginUsingId($originalUser->id);
        } else {
            Auth::logout();
        }

        $tenant = \Filament\Facades\Filament::getTenant();
        return redirect($this->resolveRedirect($originalUser ?? auth()->user(), $tenant));
    }

    protected function resolveRedirect(?Model $user, $tenant): string
    {
        $config = config('filament-impersonate.redirect_after_impersonation');

        if (is_callable($config)) {
            return $config($user, $tenant);
        }

        // Fallback: try as a named route, then treat as a plain URL
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
