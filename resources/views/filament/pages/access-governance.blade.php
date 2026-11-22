<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <div class="grid gap-6 lg:grid-cols-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('access_governance.intro.data_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('access_governance.intro.data_body') }}
                    </p>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('access_governance.intro.rules_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('access_governance.intro.rules_body') }}
                    </p>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('access_governance.intro.runtime_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('access_governance.intro.runtime_body') }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
            <p class="font-medium">{{ __('access_governance.intro.callout_title') }}</p>
            <p class="mt-1">{{ __('access_governance.intro.no_decision') }}</p>
        </div>

        @livewire(\App\Filament\Widgets\AccessPostureWidget::class)

        <x-filament::section>
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('access_governance.intro.body') }}
            </p>

            <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium">
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.protected-resources.index') }}">
                    {{ __('access_governance.links.resources') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.access-policies.index') }}">
                    {{ __('access_governance.links.policies') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.access-grants.index') }}">
                    {{ __('access_governance.links.grants') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.access-decisions.index') }}">
                    {{ __('access_governance.links.decisions') }}
                </a>
            </div>
        </x-filament::section>

        @livewire(\App\Filament\Widgets\AccessExposureWidget::class)
    </div>
</x-filament-panels::page>
