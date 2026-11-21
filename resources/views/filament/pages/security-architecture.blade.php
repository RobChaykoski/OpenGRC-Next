<x-filament-panels::page>
    @php
        $tone = [
            'evidenced' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400',
            'documented' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400',
            'stale' => 'bg-warning-50 text-warning-800 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400',
            'failing' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400',
            'gap' => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300',
        ];
    @endphp

    <div class="grid gap-6">
        <x-filament::section>
            <div class="grid gap-3">
                <label for="architecture-framework" class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('security_architecture.framework') }}
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="architecture-framework" wire:model.live="framework">
                        @foreach ($frameworks as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <p class="max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ __('security_architecture.intro') }}
                </p>
                <p class="max-w-4xl text-xs leading-5 text-gray-500 dark:text-gray-400">
                    {{ __('security_architecture.framework_help') }}
                </p>
            </div>
        </x-filament::section>

        @if (! $architecture || $architecture['outcomes'] === 0)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('security_architecture.empty') }}</p>
            </x-filament::section>
        @else
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach (['outcomes', 'evidenced', 'documented', 'stale', 'failing', 'gap'] as $key)
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ __('security_architecture.counts.'.$key) }}
                        </dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            {{ $architecture['counts'][$key] }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            @foreach ($architecture['functions'] as $function)
                <x-filament::section collapsible :collapsed="$function['counts']['outcomes'] === 0">
                    <x-slot name="heading">
                        <div class="flex flex-wrap items-center gap-3">
                            <span>{{ __('security_architecture.functions.'.$function['key'].'.label') }}</span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                {{ $function['counts']['outcomes'] }}
                            </span>
                            @if ($function['counts']['failing'] > 0)
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $tone['failing'] }}">
                                    {{ __('security_architecture.states.failing') }} {{ $function['counts']['failing'] }}
                                </span>
                            @endif
                            @if ($function['counts']['gap'] > 0)
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $tone['gap'] }}">
                                    {{ __('security_architecture.states.gap') }} {{ $function['counts']['gap'] }}
                                </span>
                            @endif
                        </div>
                    </x-slot>
                    <x-slot name="description">
                        {{ __('security_architecture.functions.'.$function['key'].'.description') }}
                    </x-slot>

                    @if ($function['outcomes'] === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('security_architecture.empty') }}
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[880px] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                                <caption class="sr-only">{{ __('security_architecture.functions.'.$function['key'].'.label') }}</caption>
                                <thead>
                                    <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.outcome') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.priority') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.controls') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.evidence') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.state') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.latest') }}</th>
                                        <th scope="col" class="px-3 py-2">{{ __('security_architecture.columns.confidence') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($function['outcomes'] as $outcome)
                                        <tr wire:key="architecture-{{ $outcome['code'] }}">
                                            <td class="px-3 py-2">
                                                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200">{{ $outcome['code'] }}</span>
                                                <p class="mt-0.5 text-sm text-gray-800 dark:text-gray-100">{{ $outcome['name'] }}</p>
                                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $outcome['category'] }}</p>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $outcome['priority'] }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $outcome['controls'] }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $outcome['evidence'] }}</td>
                                            <td class="px-3 py-2">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $tone[$outcome['state']] }}"
                                                      title="{{ __('security_architecture.state_help.'.$outcome['state']) }}">
                                                    {{ __('security_architecture.states.'.$outcome['state']) }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-300">
                                                {{ $outcome['latest_evidence_at'] ? \Illuminate\Support\Carbon::parse($outcome['latest_evidence_at'])->format('Y-m-d') : '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                {{ __('security_architecture.confidence.'.$outcome['confidence_basis']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            @endforeach

            <x-filament::section>
                <p class="max-w-5xl text-xs leading-5 text-gray-500 dark:text-gray-400">
                    {{ __('security_architecture.current_only') }}
                </p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
