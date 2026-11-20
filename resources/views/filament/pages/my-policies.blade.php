<x-filament-panels::page>
    <x-filament::section>
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
            Policy acknowledgement
        </h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-300">
            Acknowledge the policies that apply to your work. An acknowledgement is tied to the policy text; when the policy changes, it appears here again. A click records that the policy was presented and accepted — it is not proof of training.
        </p>
    </x-filament::section>

    @php($items = $this->policies())

    @if ($items === [])
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                There are no active policies to acknowledge.
            </p>
        </x-filament::section>
    @else
        <div class="grid gap-4">
            @foreach ($items as $item)
                @php($policy = $item['policy'])
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5" wire:key="policy-{{ $policy->id }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $policy->name }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $policy->code }}
                                @if ($policy->effective_date)
                                    · effective {{ $policy->effective_date->toFormattedDateString() }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($item['current'])
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200">
                                    Acknowledged
                                </span>
                            @elseif ($item['acknowledged_at'])
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-200">
                                    Policy updated — acknowledge again
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-500/20 dark:text-red-200">
                                    Not acknowledged
                                </span>
                            @endif

                            <x-filament::button
                                size="sm"
                                wire:click="acknowledge({{ $policy->id }})"
                                :color="$item['current'] ? 'gray' : 'primary'"
                            >
                                {{ $item['current'] ? 'Acknowledge again' : 'Acknowledge' }}
                            </x-filament::button>
                        </div>
                    </div>

                    @if ($item['acknowledged_at'])
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            Last acknowledged {{ $item['acknowledged_at']->diffForHumans() }}.
                        </p>
                    @endif

                    <div class="mt-3">
                        <a class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ $this->policyUrl($policy) }}">
                            Read the policy
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
