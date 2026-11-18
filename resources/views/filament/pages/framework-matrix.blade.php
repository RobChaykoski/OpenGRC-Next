<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="max-w-xl">
            <label for="framework" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                {{ __('assurance.framework.label') }}
            </label>

            <x-filament::input.wrapper>
                <x-filament::input.select
                    id="framework"
                    wire:model.live="framework"
                >
                    @forelse ($frameworks as $code => $name)
                        <option value="{{ $code }}" @selected($framework === $code)>{{ $name }}</option>
                    @empty
                        <option value="">{{ __('assurance.framework.none') }}</option>
                    @endforelse
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('assurance.framework.help') }}
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
