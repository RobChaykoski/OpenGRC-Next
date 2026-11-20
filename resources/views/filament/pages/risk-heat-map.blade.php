<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <div class="grid gap-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                            Active risks by likelihood and impact
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                            Two views of the same active register: the inherent score before treatment and the
                            residual score after the controls you have recorded. A cell links to the filtered risk
                            list. The colour shows the score band, not whether the risk is within appetite.
                        </p>
                    </div>

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                            <dt class="text-gray-500 dark:text-gray-400">Active risks</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $total }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                            <dt class="text-gray-500 dark:text-gray-400">Inherent very high</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $veryHigh }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
                    @foreach ([['Very low', '#bbf7d0'], ['Low', '#bfdbfe'], ['Moderate', '#fef08a'], ['High', '#fed7aa'], ['Very high', '#fecaca']] as [$legend, $color])
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-sm border border-black/10" style="background-color: {{ $color }}"></span>
                            {{ $legend }}
                        </span>
                    @endforeach
                </div>

                <div class="grid gap-8 xl:grid-cols-2">
                    @foreach (['Inherent' => $inherent, 'Residual' => $residual] as $heading => $matrix)
                        @php $prefix = $heading === 'Inherent' ? 'inherent' : 'residual'; @endphp

                        <div>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }} risk</h3>

                            <table class="mt-3 w-full table-fixed border-collapse text-center text-sm">
                                <thead>
                                    <tr>
                                        <th class="w-24 border border-gray-200 px-2 py-2 text-left text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                            Likelihood \ Impact
                                        </th>
                                        @foreach ([1, 2, 3, 4, 5] as $impact)
                                            <th class="border border-gray-200 px-2 py-2 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                                {{ $impact }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($levels as $likelihood)
                                        <tr>
                                            <th class="border border-gray-200 px-2 py-2 text-left text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                                                {{ $likelihood }}
                                            </th>

                                            @foreach ([1, 2, 3, 4, 5] as $impact)
                                                @php
                                                    $score = $likelihood * $impact;
                                                    $count = $matrix[$likelihood.'-'.$impact] ?? 0;
                                                    $url = \App\Filament\Resources\RiskResource::getUrl('index').'?'.http_build_query([
                                                        'tableFilters' => [
                                                            $prefix.'_likelihood' => ['value' => (string) $likelihood],
                                                            $prefix.'_impact' => ['value' => (string) $impact],
                                                            'is_active' => ['value' => '1'],
                                                        ],
                                                    ]);
                                                @endphp

                                                <td class="border border-gray-200 p-0 align-middle dark:border-white/10" style="background-color: {{ $colors[$score] }}; color: #111827;">
                                                    @if ($count > 0)
                                                        <a
                                                            href="{{ $url }}"
                                                            title="{{ \App\Enums\RiskLevel::fromScore($score)->getLabel() }} ({{ $score }}): {{ $count }} risk{{ $count === 1 ? '' : 's' }}"
                                                            class="block px-2 py-3 font-semibold underline"
                                                        >
                                                            {{ $count }}
                                                        </a>
                                                    @else
                                                        <span class="block px-2 py-3 text-gray-500">·</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Rows are likelihood, columns are impact. Score = likelihood × impact.
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
