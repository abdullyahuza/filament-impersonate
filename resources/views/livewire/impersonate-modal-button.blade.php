<div>
    @if (filamentImpersonateEnabled())
        {{ $this->SwitchAction }}
        <x-filament-actions::modals />
    @endif
</div>
