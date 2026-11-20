<x-filament-panels::page>
    @php
        $summary = $report['summary'] ?? [];
        $sources = $report['sources'] ?? [];
        $rows = $this->filteredRows();
        $tabs = [
            'debian' => ['label' => __('coverage.deploy.tabs.debian'), 'command' => $commands['wazuh_debian'] ?? null],
            'rhel' => ['label' => __('coverage.deploy.tabs.rhel'), 'command' => $commands['wazuh_rhel'] ?? null],
            'windows' => ['label' => __('coverage.deploy.tabs.windows'), 'command' => $commands['wazuh_windows'] ?? null],
            'patchmon' => ['label' => __('coverage.deploy.tabs.patchmon'), 'command' => $commands['patchmon'] ?? null],
            'fleet' => ['label' => __('coverage.deploy.tabs.fleet'), 'command' => $commands['fleet'] ?? null],
        ];
        $cards = [
            [
                'label' => __('coverage.summary.documented'),
                'value' => ($summary['documented'] ?? 0).' / '.($summary['assets_total'] ?? 0),
                'detail' => __('coverage.summary.documented_detail', ['total' => $summary['assets_total'] ?? 0]),
                'tone' => 'gray',
            ],
            [
                'label' => __('coverage.summary.wazuh'),
                'value' => ($summary['wazuh_covered'] ?? 0).' · '.__('coverage.summary.percent', ['percent' => $summary['wazuh_percent'] ?? 0]),
                'detail' => __('coverage.sources.wazuh'),
                'tone' => ($summary['wazuh_percent'] ?? 0) >= 90 ? 'success' : 'warning',
            ],
            [
                'label' => __('coverage.summary.patchmon'),
                'value' => ($summary['patchmon_covered'] ?? 0).' · '.__('coverage.summary.percent', ['percent' => $summary['patchmon_percent'] ?? 0]),
                'detail' => __('coverage.sources.patchmon'),
                'tone' => ($summary['patchmon_percent'] ?? 0) >= 90 ? 'success' : 'warning',
            ],
            [
                'label' => __('coverage.summary.discovery'),
                'value' => ($summary['discovery_covered'] ?? 0).' · '.__('coverage.summary.percent', ['percent' => $summary['discovery_percent'] ?? 0]),
                'detail' => __('coverage.sources.discovery'),
                'tone' => ($summary['discovery_percent'] ?? 0) >= 80 ? 'success' : 'warning',
            ],
            [
                'label' => __('coverage.summary.unowned'),
                'value' => $summary['unowned'] ?? 0,
                'detail' => __('coverage.summary.unowned_detail'),
                'tone' => ($summary['unowned'] ?? 0) > 0 ? 'danger' : 'success',
            ],
            [
                'label' => __('coverage.summary.critical'),
                'value' => $summary['critical_gaps'] ?? 0,
                'detail' => __('coverage.summary.critical_detail'),
                'tone' => ($summary['critical_gaps'] ?? 0) > 0 ? 'danger' : 'success',
            ],
        ];

        $toneClass = fn (string $tone): string => match ($tone) {
            'success' => 'text-emerald-600 dark:text-emerald-400',
            'warning' => 'text-amber-600 dark:text-amber-400',
            'danger' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-900 dark:text-white',
        };
    @endphp

    <div class="grid gap-6">
        <x-filament::section>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('coverage.intro.title') }}
            </h2>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('coverage.intro.body') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium">
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.pages.data-posture') }}">
                    {{ __('coverage.links.posture') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.assets.index') }}">
                    {{ __('coverage.links.assets') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.discovery-findings.index') }}">
                    {{ __('coverage.links.findings') }}
                </a>
            </div>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $card['label'] }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold {{ $toneClass($card['tone']) }}">
                        {{ $card['value'] }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $card['detail'] }}
                    </p>
                </div>
            @endforeach
        </div>

        <x-filament::section>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('coverage.table.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('coverage.intro.body') }}
                    </p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        {{ __('coverage.filters.gap') }}
                        <select
                            wire:model.live="gapFilter"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="all">{{ __('coverage.filters.all') }}</option>
                            @foreach ($this->gapOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        {{ __('coverage.filters.search') }}
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('coverage.filters.search_placeholder') }}"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                    </label>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">{{ __('coverage.table.priority') }}</th>
                            <th class="px-3 py-2">{{ __('coverage.table.asset') }}</th>
                            <th class="px-3 py-2">{{ __('coverage.table.sources') }}</th>
                            <th class="px-3 py-2">{{ __('coverage.table.owner') }}</th>
                            <th class="px-3 py-2">{{ __('coverage.table.gaps') }}</th>
                            <th class="px-3 py-2">{{ __('coverage.table.recommendation') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($rows as $row)
                            <tr wire:key="asset-{{ $row['key'] }}">
                                <td class="px-3 py-3">
                                    @php
                                        $priorityClass = match ($row['priority']) {
                                            'critical' => 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200',
                                            'high' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
                                            'medium' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $priorityClass }}">
                                        {{ __('coverage.priority.'.$row['priority']) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ $row['asset_name'] }}
                                    @if ($row['last_seen'])
                                        <p class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                                            {{ __('coverage.table.last_seen') }}: {{ $row['last_seen']->diffForHumans() }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @php
                                            $badge = function (bool $present, bool $stale = false): string {
                                                if (! $present) {
                                                    return 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400';
                                                }

                                                return $stale
                                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200'
                                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200';
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge($row['documented']) }}">
                                            {{ __('coverage.sources.opengrc') }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge($row['wazuh'] !== null, $row['wazuh']['stale'] ?? false) }}">
                                            {{ __('coverage.sources.wazuh') }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge($row['patchmon'] !== null, $row['patchmon']['stale'] ?? false) }}">
                                            {{ __('coverage.sources.patchmon') }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge($row['discovery'] !== null, $row['discovery']['stale'] ?? false) }}">
                                            {{ __('coverage.sources.discovery') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                        {{ $row['owner'] ? __('coverage.table.assigned') : __('coverage.table.unassigned') }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($row['gaps'] as $gap)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                                {{ __('coverage.gaps.'.$gap) }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-emerald-600 dark:text-emerald-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-200">
                                    {{ $row['recommendation'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('coverage.table.empty_heading') }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('coverage.table.empty_description') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('coverage.deploy.title') }}
            </h2>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('coverage.deploy.body') }}
            </p>

            @if ($this->canDeploy())
                <div class="mt-4" x-data="{ tab: 'debian' }">
                    <div class="flex flex-wrap gap-2" role="tablist">
                        @foreach ($tabs as $key => $tab)
                            <button
                                type="button"
                                role="tab"
                                x-on:click="tab = '{{ $key }}'"
                                x-bind:aria-selected="tab === '{{ $key }}'"
                                x-bind:class="tab === '{{ $key }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                            >
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    @foreach ($tabs as $key => $tab)
                        <div x-show="tab === '{{ $key }}'" class="mt-4">
                            @if ($tab['command'])
                                <div class="relative">
                                    <button
                                        type="button"
                                        x-on:click="navigator.clipboard.writeText($refs['cmd-{{ $key }}'].innerText)"
                                        class="absolute right-3 top-3 rounded-md bg-white/10 px-2 py-1 text-xs font-medium text-white hover:bg-white/20"
                                    >
                                        {{ __('coverage.deploy.copy') }}
                                    </button>
                                    <pre x-ref="cmd-{{ $key }}" class="overflow-x-auto rounded-lg bg-gray-950 p-4 pt-10 text-xs leading-6 text-gray-100">{{ $tab['command'] }}</pre>
                                </div>
                            @else
                                <p class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                                    {{ __('coverage.deploy.not_configured') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('coverage.deploy.notes') }}</h3>
                    <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        @foreach ($notes as $note)
                            <li class="flex gap-2">
                                <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-primary-500"></span>
                                <span>{{ $note }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="mt-4 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                    {{ __('coverage.deploy.only_writers') }}
                </p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('coverage.source_status.title') }}
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ __('coverage.source_status.body') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach (['wazuh', 'patchmon', 'discovery', 'netbox', 'ralph', 'fleet'] as $source)
                    @php
                        $configured = (bool) ($sources[$source] ?? false);
                        $label = __('coverage.sources.'.$source);
                        $status = $configured
                            ? __('coverage.sources.configured')
                            : (in_array($source, ['netbox', 'ralph', 'fleet'], true)
                                ? __('coverage.sources.not_integrated')
                                : __('coverage.sources.not_configured'));
                    @endphp
                    <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-700 dark:border-white/10 dark:text-gray-200">
                        {{ $label }}
                        <span class="{{ $configured ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $status }}
                        </span>
                    </span>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
