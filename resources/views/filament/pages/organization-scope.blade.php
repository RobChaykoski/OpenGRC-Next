<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('scoping.intro.title') }}
            </h2>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('scoping.intro.body') }}
            </p>
        </x-filament::section>

        <form wire:submit="assess" class="grid gap-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    {{ __('scoping.form.assess') }}
                </x-filament::button>
            </div>
        </form>

        @if ($assessment)
            @php
                $frameworks = $assessment['framework_matrix'] ?? [];
                $controls = $assessment['controls'] ?? [];
                $documents = $assessment['documents'] ?? [];
                $gaps = $assessment['gaps'] ?? [];
            @endphp

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => __('scoping.results.frameworks'), 'value' => count($frameworks), 'tone' => 'text-primary-600 dark:text-primary-400'],
                    ['label' => __('scoping.results.controls'), 'value' => count($controls), 'tone' => 'text-primary-600 dark:text-primary-400'],
                    ['label' => __('scoping.results.documents'), 'value' => count($documents), 'tone' => 'text-primary-600 dark:text-primary-400'],
                    ['label' => __('scoping.results.gaps'), 'value' => count($gaps), 'tone' => count($gaps) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'],
                ] as $card)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold {{ $card['tone'] }}">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <x-filament::section :heading="__('scoping.results.frameworks')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">{{ __('scoping.results.framework') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.category') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.priority') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.confidence') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.why') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($frameworks as $framework)
                                <tr>
                                    <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">{{ $framework['framework'] }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $framework['category'] }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $framework['priority'] === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200' }}">
                                            {{ __('scoping.priority.'.$framework['priority']) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ __('scoping.confidence.'.$framework['confidence']) }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $framework['justification'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section :heading="__('scoping.results.controls')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">{{ __('scoping.results.code') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.control') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.category') }}</th>
                                <th class="px-3 py-2">{{ __('scoping.results.used_by') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($controls as $control)
                                <tr>
                                    <td class="px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $control['code'] }}</td>
                                    <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">{{ $control['name'] }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $control['category'] }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ implode(', ', $control['frameworks'] ?? []) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-filament::section :heading="__('scoping.results.documents')">
                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                        @forelse ($documents as $document)
                            <li class="flex items-start justify-between gap-4">
                                <span>{{ $document['document'] }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $document['review_frequency'] ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="text-gray-500 dark:text-gray-400">—</li>
                        @endforelse
                    </ul>
                </x-filament::section>

                <x-filament::section :heading="__('scoping.results.gaps')">
                    @if (count($gaps) > 0)
                        <ul class="space-y-2 text-sm text-amber-800 dark:text-amber-200">
                            @foreach ($gaps as $gap)
                                <li class="flex gap-2">
                                    <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                                    <span>{{ $gap }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ __('scoping.results.no_gaps') }}</p>
                    @endif
                </x-filament::section>
            </div>

            <x-filament::section :heading="__('scoping.results.note_title')">
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ __('scoping.results.note_body') }}</p>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ __('scoping.results.import_hint') }}</p>
                <div class="mt-4">
                    <a class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.pages.assurance-matrix') }}">
                        {{ __('scoping.results.open_matrix') }}
                    </a>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('scoping.results.empty') }}</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
