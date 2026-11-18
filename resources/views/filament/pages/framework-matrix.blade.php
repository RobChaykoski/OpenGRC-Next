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

        @php($score = $this->score())
        @php($trend = $this->trend())

        <x-filament::section>
            <x-slot name="heading">
                {{ __('assurance.score.heading') }}
            </x-slot>

            @if ($score)
                <div class="grid gap-6 sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('assurance.score.total') }}
                        </div>
                        <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">
                            {{ $score['score'] }}<span class="text-base font-normal text-gray-500">/100</span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('assurance.score.counts', ['reviewed' => $score['counts']['reviewed'], 'outcomes' => $score['outcomes']]) }}
                        </div>
                        <div class="mt-1 text-xs {{ $trend && $trend['change'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                            @if ($trend)
                                {{ __('assurance.score.trend', ['change' => ($trend['change'] >= 0 ? '+' : '').$trend['change'], 'days' => $trend['days']]) }}
                            @else
                                {{ __('assurance.score.no_trend') }}
                            @endif
                        </div>
                    </div>

                    @foreach (['reviewed', 'evidence', 'quality', 'automation', 'monitoring'] as $component)
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('assurance.score.components.'.$component) }}
                            </div>
                            <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                                {{ $score['components'][$component]['value'] }}%
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('assurance.score.weight', ['weight' => $score['components'][$component]['weight']]) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('assurance.score.help') }}
                </p>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('assurance.score.unavailable') }}
                </p>
            @endif
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
