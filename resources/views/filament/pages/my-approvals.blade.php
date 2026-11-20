<x-filament-panels::page>
    <div class="grid gap-6">
        <p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
            You are the current approver for these requests. Approving moves the request to the next step; rejecting
            ends the chain and skips the remaining steps.
        </p>

        @if ($approvals->isEmpty())
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    No approval steps are waiting for you.
                </p>
            </x-filament::section>
        @else
            @foreach ($approvals as $step)
                <x-filament::section wire:key="approval-step-{{ $step->id }}">
                    <div class="grid gap-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $step->request?->title }}
                                </h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    Step {{ $step->sequence }} of {{ $step->request?->steps->count() }}
                                    @if ($step->request?->requestedBy)
                                        · requested by {{ $step->request->requestedBy->name }}
                                    @endif
                                </p>
                            </div>

                            <p class="text-sm font-medium {{ $step->request?->isOverdue() ? 'text-danger-600 dark:text-danger-400' : 'text-gray-600 dark:text-gray-300' }}">
                                @if ($step->request?->due_at)
                                    Due {{ $step->request->due_at->toFormattedDateString() }}
                                    @if ($step->request->isOverdue())
                                        · overdue
                                    @endif
                                @else
                                    No due date
                                @endif
                            </p>
                        </div>

                        @if ($step->request?->description)
                            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
                                {{ $step->request->description }}
                            </div>
                        @endif

                        <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                            Comment
                            <textarea
                                wire:model="comments.{{ $step->id }}"
                                rows="3"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                placeholder="Optional when approving; required when rejecting."
                            ></textarea>
                        </label>

                        <div class="flex flex-wrap gap-3">
                            <x-filament::button
                                wire:click="approve({{ $step->id }})"
                                color="success"
                                icon="heroicon-o-check-badge"
                            >
                                Approve
                            </x-filament::button>

                            <x-filament::button
                                wire:click="reject({{ $step->id }})"
                                color="danger"
                                icon="heroicon-o-x-circle"
                            >
                                Reject
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
