<?php

namespace Abdullyahuza\FilamentImpersonate\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Contracts\HasActions;

use Illuminate\Foundation\Auth\User;
use Livewire\Component;
use Filament\Actions\Action;

class ImpersonateModal extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string $search = '';

    public function startImpersonation($userId)
    {
        session(['impersonate' => $userId]);

        return redirect()->route('filament.admin.pages.dashboard'); // adjust if needed
    }

    public function render()
    {
        $users = User::query()
            ->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
            })
            ->limit(10)
            ->get();

        return view('filament-impersonate::livewire.impersonate-modal', compact('users'));
    }

    public function openModal()
    {
        $this->dispatchBrowserEvent('open-impersonate-modal');
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->action(fn () => dd(1));
    }

}