<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <div class="grid gap-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                        Source framework
                        <select
                            wire:model.live="source"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            @foreach ($standards as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                        Target framework
                        <select
                            wire:model.live="target"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            @foreach ($standards as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <p class="max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Approved crosswalks are reviewed mappings, not textual equivalence and not evidence. A mapping
                    does not close a coverage gap; it records why a reviewer considers two requirements to serve the
                    same programme intent.
                </p>
            </div>
        </x-filament::section>

        @if (! $hasPair)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Select two different frameworks with imported controls.
                </p>
            </x-filament::section>
        @else
            <dl class="grid grid-cols-2 gap-4 text-sm lg:grid-cols-5">
                <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <dt class="text-gray-500 dark:text-gray-400">Approved mappings</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $approvedCount }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <dt class="text-gray-500 dark:text-gray-400">Draft mappings</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $draftCount }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $sourceStandard?->code }} controls</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $leftTotal }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <dt class="text-gray-500 dark:text-gray-400">Without an approved mapping</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $leftUnmapped }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $targetStandard?->code }} without a mapping</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $rightUnmapped }}</dd>
                </div>
            </dl>

            <x-filament::section>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">{{ $sourceStandard?->code }} control</th>
                                <th class="px-3 py-2">{{ $targetStandard?->code }} control</th>
                                <th class="px-3 py-2">Confidence</th>
                                <th class="px-3 py-2">Reviewed by</th>
                                <th class="px-3 py-2">Reviewed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-3 py-3">
                                        @if ($row['left'])
                                            <a
                                                href="{{ \App\Filament\Resources\ControlResource::getUrl('view', ['record' => $row['left']]) }}"
                                                class="font-medium text-primary-600 underline dark:text-primary-400"
                                            >
                                                {{ $row['left']->code }}
                                            </a>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['left']->title }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if ($row['right'])
                                            <a
                                                href="{{ \App\Filament\Resources\ControlResource::getUrl('view', ['record' => $row['right']]) }}"
                                                class="font-medium text-primary-600 underline dark:text-primary-400"
                                            >
                                                {{ $row['right']->code }}
                                            </a>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['right']->title }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $row['confidence']?->getLabel() }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $row['reviewer'] ?? '—' }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">
                                        {{ $row['reviewed_at']?->format('M j, Y') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                        No approved crosswalks between these frameworks.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
