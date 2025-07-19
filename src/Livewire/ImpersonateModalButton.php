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
use Illuminate\Foundation\Auth\User;
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
        $originalUser = isImpersonating() ? ($this->getUserModel())::find($impersonatorId) : null;
        $userName = filamentImpersonateDisplayName(auth()->user());
        $roleNames = auth()->user()->getRoleNames()->implode(', ');

        if (isImpersonating() && $originalUser) {
            return Action::make('Switch')
                ->label(function() use($userName, $roleNames) {
                    return "You're impersonating {$userName} ({$roleNames}), click to stop impersonation";
                })
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
                            ->options(
                                ($this->getUserModel())::where('id', '!=', auth()->id())
                                    ->whereNotIn('id', config('filament-impersonate.excluded_user_ids', []))
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => filamentImpersonateDisplayName($user)])
                            )
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
        if (!empty($data['user_id'])) {
            return ($this->getUserModel())::find($data['user_id']);
        }

        if (!empty($data['role_ids'])) {
            return ($this->getUserModel())::where('id', '!=', auth()->id())
                ->whereHas('roles', fn ($q) => $q->whereIn('id', $data['role_ids']))
                ->withCount(['roles as matching_roles_count' => fn ($q) => $q->whereIn('id', $data['role_ids'])])
                ->having('matching_roles_count', count($data['role_ids']))
                ->first();
        }

        if (!empty($data['permission_ids'])) {
            return ($this->getUserModel())::where('id', '!=', auth()->id())
                ->whereHas('permissions', fn ($q) => $q->whereIn('id', $data['permission_ids']))
                ->withCount(['permissions as matching_permissions_count' => fn ($q) => $q->whereIn('id', $data['permission_ids'])])
                ->having('matching_permissions_count', count($data['permission_ids']))
                ->first();
        }

        return null;
    }

    protected function impersonate(Model $targetUser)
    {
        Auth::loginUsingId($targetUser->id);
        return redirect()->route(config('filament-impersonate.redirect_after_impersonation'));

    }

    protected function stopImpersonation()
    {
        $originalId = session(config('filament-impersonate.session_key', 'filament_impersonator_id'));

        if ($originalId && ($this->getUserModel())::find($originalId)) {
            Auth::loginUsingId($originalId);
        } else {
            Auth::logout();
        }

        return redirect()->route(config('filament-impersonate.redirect_after_impersonation'));

    }

    protected function getUserModel(): string
    {
        return filamentImpersonateUserModel(); // or config('filament-impersonate.user_model')
    }

}
