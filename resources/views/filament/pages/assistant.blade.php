<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <x-slot name="heading">{{ __('assistant.form.heading') }}</x-slot>
            <x-slot name="description">{{ __('assistant.form.description') }}</x-slot>

            <form wire:submit="ask" class="grid gap-4">
                <div class="grid gap-4 md:grid-cols-[18rem,1fr]">
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('assistant.form.framework') }}
                        <select
                            wire:model="framework"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">{{ __('assistant.form.any_framework') }}</option>
                            @foreach ($frameworks as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                            {{ __('assistant.form.framework_help') }}
                        </span>
                    </label>

                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('assistant.form.question') }}
                        <textarea
                            wire:model="question"
                            rows="3"
                            maxlength="500"
                            placeholder="{{ __('assistant.form.placeholder') }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        ></textarea>
                        @error('question')
                            <span class="text-xs font-normal text-danger-600 dark:text-danger-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button type="submit" icon="heroicon-o-sparkles">
                        {{ __('assistant.form.ask') }}
                    </x-filament::button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('assistant.form.read_only') }}
                    </span>
                </div>

                <div class="grid gap-2 border-t border-gray-100 pt-4 dark:border-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('assistant.form.suggestions') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($intents as $intent)
                            <button
                                type="button"
                                wire:click="useSuggestion(@js($intent['example']))"
                                class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                                title="{{ $intent['description'] }}"
                            >
                                {{ $intent['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </form>
        </x-filament::section>

        @if ($status !== null)
            @php
                $statusTone = match ($status) {
                    'answered' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400',
                    'facts_only' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400',
                    'unsupported', 'needs_input' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400',
                    'blocked_dlp', 'quota_exceeded', 'error' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400',
                    default => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300',
                };
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex flex-wrap items-center gap-3">
                        <span>{{ __('assistant.result.heading') }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusTone }}">
                            {{ $statusLabel }}
                        </span>
                        @if ($activeRunId !== null)
                            <span class="text-xs font-normal text-gray-400">#{{ $activeRunId }}</span>
                        @endif
                    </div>
                </x-slot>

                @if ($notice)
                    <div class="mb-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                        {{ $notice }}
                    </div>
                @endif

                @if ($injectionFlags !== [])
                    <div class="mb-4 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300">
                        {{ __('assistant.result.injection') }}
                    </div>
                @endif

                @if ($counts !== [])
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($counts as $count)
                            <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $count['label'] }}
                                </dt>
                                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ $count['value'] }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($answer)
                    <div class="mt-5">
                        <div class="mb-2 flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ __('assistant.result.interpretation') }}
                            </h3>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                {{ __('assistant.result.ai_label') }}
                            </span>
                        </div>
                        <div class="whitespace-pre-line rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm leading-6 text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">{{ $answer }}</div>
                    </div>
                @endif

                @if ($facts !== [])
                    <div class="mt-5">
                        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('assistant.result.facts') }}
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                                <thead>
                                    <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        @foreach ($columns as $label)
                                            <th class="px-3 py-2">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($facts as $row)
                                        <tr>
                                            @foreach ($columns as $key => $label)
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $row[$key] ?? '—' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($intent === 'overdue_tests' && $activeRunId !== null)
                    <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-sm leading-6 text-gray-700 dark:text-gray-200">
                            {{ __('assistant.propose.help') }}
                        </p>
                        <div class="mt-3">
                            <x-filament::button
                                size="sm"
                                color="warning"
                                wire:click="proposeTestDates"
                                wire:confirm="{{ __('assistant.propose.confirm') }}"
                            >
                                {{ __('assistant.propose.action') }}
                            </x-filament::button>
                        </div>
                    </div>
                @endif

                <p class="mt-5 border-t border-gray-100 pt-4 text-xs leading-5 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    {{ __('assistant.result.boundary') }}
                </p>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">{{ __('assistant.recent.heading') }}</x-slot>
            <x-slot name="description">{{ __('assistant.recent.description') }}</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">{{ __('assistant.recent.when') }}</th>
                            <th class="px-3 py-2">{{ __('assistant.recent.question') }}</th>
                            @if ($isAdmin)
                                <th class="px-3 py-2">{{ __('assistant.recent.user') }}</th>
                            @endif
                            <th class="px-3 py-2">{{ __('assistant.recent.question_type') }}</th>
                            <th class="px-3 py-2">{{ __('assistant.recent.status') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($recentRuns as $run)
                            <tr wire:key="assistant-run-{{ $run->id }}">
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $run->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-200">
                                    {{ \Illuminate\Support\Str::limit($run->question, 90) }}
                                </td>
                                @if ($isAdmin)
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $run->user?->name ?? '—' }}</td>
                                @endif
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $run->intent?->getLabel() ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        {{ $run->status->getLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        wire:click="showRun({{ $run->id }})"
                                        class="text-xs font-medium text-primary-600 underline hover:text-primary-500 dark:text-primary-400"
                                    >
                                        {{ __('assistant.recent.view') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('assistant.recent.empty') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">{{ __('assistant.catalog.heading') }}</x-slot>
            <x-slot name="description">{{ __('assistant.catalog.description') }}</x-slot>

            <dl class="grid gap-4 md:grid-cols-2">
                @foreach ($intents as $intent)
                    <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10">
                        <dt class="text-sm font-semibold text-gray-950 dark:text-white">{{ $intent['label'] }}</dt>
                        <dd class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $intent['description'] }}</dd>
                        <dd class="mt-2 text-xs italic text-gray-500 dark:text-gray-400">“{{ $intent['example'] }}”</dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-gray-400">
                {{ __('assistant.catalog.boundary') }}
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
