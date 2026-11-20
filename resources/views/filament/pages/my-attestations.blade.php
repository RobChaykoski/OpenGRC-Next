<x-filament-panels::page>
    <div class="grid gap-6">
        <p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
            Attesting confirms that the implementation is still operated as described. If it is no longer true,
            decline and raise a finding instead of attesting.
        </p>

        @if ($attestations->isEmpty())
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    No control attestations are waiting for you.
                </p>
            </x-filament::section>
        @else
            @foreach ($attestations as $attestation)
                <x-filament::section wire:key="attestation-{{ $attestation->id }}">
                    <div class="grid gap-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $attestation->implementation?->title ?? 'Implementation' }}
                                </h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $attestation->implementation?->code }}
                                    @if ($attestation->requestedBy)
                                        · requested by {{ $attestation->requestedBy->name }}
                                    @endif
                                </p>
                            </div>

                            <p class="text-sm font-medium {{ $attestation->isOverdue() ? 'text-danger-600 dark:text-danger-400' : 'text-gray-600 dark:text-gray-300' }}">
                                Due {{ $attestation->due_at->toFormattedDateString() }}
                                @if ($attestation->isOverdue())
                                    · overdue
                                @endif
                            </p>
                        </div>

                        @if ($attestation->request_note)
                            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
                                {{ $attestation->request_note }}
                            </div>
                        @endif

                        <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                            Your statement
                            <textarea
                                wire:model="statements.{{ $attestation->id }}"
                                rows="3"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                placeholder="What is true, and any caveat."
                            ></textarea>
                        </label>

                        <div class="flex flex-wrap gap-3">
                            <x-filament::button
                                wire:click="attest({{ $attestation->id }})"
                                color="success"
                                icon="heroicon-o-check-badge"
                            >
                                Attest
                            </x-filament::button>

                            <x-filament::button
                                wire:click="decline({{ $attestation->id }})"
                                color="danger"
                                icon="heroicon-o-x-circle"
                            >
                                Decline
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
