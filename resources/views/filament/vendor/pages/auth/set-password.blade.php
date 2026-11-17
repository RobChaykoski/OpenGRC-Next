<x-filament-panels::page>
    <x-filament-panels::form wire:submit="setPassword">
        {{ $this->form }}

        <x-filament::button type="submit">
            Set Password
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
