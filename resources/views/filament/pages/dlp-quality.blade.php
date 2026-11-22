<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <div class="grid gap-6">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        False-positive rate by identifier type
                    </h2>
                    <p class="mt-1 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                        The rate is <strong>false positives ÷ reviewed findings</strong> for the last
                        {{ $summary['window_days'] }} days. Reviewed means a person recorded a disposition on the
                        finding. A type with fewer reviewed findings than its minimum sample is
                        <strong>insufficient data</strong>, not on target.
                    </p>
                    <p class="mt-2 max-w-4xl text-xs leading-5 text-gray-500 dark:text-gray-400">
                        There is no single industry-standard false-positive percentage for DLP. Each target is a
                        programme target with the requirement or benchmark recorded in the reference column. Change a
                        target only with a reason; do not copy a vendor benchmark into the config silently.
                    </p>
                    <p class="mt-2 max-w-4xl text-xs leading-5 text-gray-500 dark:text-gray-400">
                        <strong>Accepted</strong> counts reviewed findings currently covered by an in-force suppression.
                        A suppression is a time-boxed acceptance, not a review outcome: it does not change the rate, and
                        the finding stays in the queue with the suppression visible.
                    </p>
                </div>

                <dl class="grid grid-cols-2 gap-4 text-sm lg:grid-cols-5">
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <dt class="text-gray-500 dark:text-gray-400">Reviewed findings</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $summary['reviewed'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <dt class="text-gray-500 dark:text-gray-400">False positives</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $summary['false_positives'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <dt class="text-gray-500 dark:text-gray-400">Accepted by suppression</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $summary['suppressed'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <dt class="text-gray-500 dark:text-gray-400">Overall rate</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                            {{ $summary['rate'] === null ? '—' : number_format($summary['rate'], 1).'%' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <dt class="text-gray-500 dark:text-gray-400">Types on target</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                            {{ $summary['targets_met'] }} / {{ count($summary['rows']) }}
                        </dd>
                    </div>
                </dl>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Identifier type</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2 text-right">Reviewed</th>
                                <th class="px-3 py-2 text-right">False positives</th>
                                <th class="px-3 py-2 text-right">Accepted</th>
                                <th class="px-3 py-2 text-right">Rate</th>
                                <th class="px-3 py-2 text-right">Target</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($summary['rows'] as $row)
                                @php
                                    [$statusLabel, $statusClass] = match ($row['status']) {
                                        'on_target' => ['On target', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'],
                                        'above_target' => ['Above target', 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'],
                                        default => ['Insufficient data', 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300'],
                                    };
                                @endphp
                                <tr>
                                    <td class="px-3 py-3 font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $row['category'] }}</td>
                                    <td class="px-3 py-3 text-right text-gray-600 dark:text-gray-300">
                                        {{ $row['reviewed'] }}
                                        <span class="text-xs text-gray-400">/ {{ $row['minimum_sample'] }} min</span>
                                    </td>
                                    <td class="px-3 py-3 text-right text-gray-600 dark:text-gray-300">{{ $row['false_positives'] }}</td>
                                    <td class="px-3 py-3 text-right text-gray-600 dark:text-gray-300">{{ $row['suppressed'] }}</td>
                                    <td class="px-3 py-3 text-right font-medium text-gray-950 dark:text-white">
                                        {{ $row['rate'] === null ? '—' : number_format($row['rate'], 1).'%' }}
                                    </td>
                                    <td class="px-3 py-3 text-right text-gray-600 dark:text-gray-300">
                                        {{ number_format($row['target'], 1) }}%
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs px-3 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['reference'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                    False positive: the detector should not have raised the finding. Benign / accepted is a real match
                    the programme allows, so it is not counted as a false positive. Undetermined findings are excluded
                    from the rate until someone reviews them.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
