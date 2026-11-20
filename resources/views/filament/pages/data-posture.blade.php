<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <div class="grid gap-6 lg:grid-cols-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('dspm.intro.dlp_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('dspm.intro.dlp_body') }}
                    </p>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('dspm.intro.dspm_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('dspm.intro.dspm_body') }}
                    </p>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('dspm.intro.why_title') }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('dspm.intro.why_body') }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
            <p class="font-medium">{{ __('dspm.callout.title') }}</p>
            <p class="mt-1">{{ __('dspm.callout.body') }}</p>
        </div>

        @livewire(\App\Filament\Widgets\DspmPostureWidget::class)

        <x-filament::section :heading="__('dspm.catalog.heading')" :description="__('dspm.catalog.description')">
            @livewire(\App\Filament\Widgets\CatalogGovernanceWidget::class)
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            @livewire(\App\Filament\Widgets\DspmCategoryChart::class)
            @livewire(\App\Filament\Widgets\DspmAgingChart::class)
        </div>

        @livewire(\App\Filament\Widgets\DspmExposedAssetsWidget::class)

        @livewire(\App\Filament\Widgets\DlpOperationsWidget::class)

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section :heading="__('dspm.learning.read_title')">
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ __('dspm.learning.read_body') }}
                </p>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-gray-700 dark:text-gray-200">
                    @foreach (__('dspm.learning.read_items') as $item)
                        <li class="flex gap-2">
                            <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-primary-500"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>

            <x-filament::section :heading="__('dspm.learning.employer_title')">
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ __('dspm.learning.employer_body') }}
                </p>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-gray-700 dark:text-gray-200">
                    @foreach (__('dspm.learning.employer_items') as $item)
                        <li class="flex gap-2">
                            <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-primary-500"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        </div>

        <x-filament::section :heading="__('dspm.next.title')">
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('dspm.next.body') }}
            </p>
            <ul class="mt-4 grid gap-2 text-sm leading-6 text-gray-700 dark:text-gray-200 md:grid-cols-2">
                @foreach (__('dspm.next.items') as $item)
                    <li class="flex gap-2">
                        <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-primary-500"></span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium">
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.discovery-findings.index') }}">
                    {{ __('dspm.links.findings') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.discovery-scans.index') }}">
                    {{ __('dspm.links.scans') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.resources.assets.index') }}">
                    {{ __('dspm.links.assets') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.app.pages.assurance-matrix') }}">
                    {{ __('dspm.links.frameworks') }}
                </a>
                <a class="text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ route('filament.admin.resources.dlp-findings.index') }}">
                    {{ __('dspm.links.dlp') }}
                </a>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
