<div x-data="{ copied: false }" class="space-y-3">
    <label class="block text-sm font-medium text-gray-950 dark:text-white">
        {{ __('audit-portal.admin.show_link') }}
    </label>

    <div class="flex items-stretch gap-2">
        <input
            type="text"
            readonly
            value="{{ $url }}"
            onclick="this.select()"
            class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-xs text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
        >
        <button
            type="button"
            x-on:click="navigator.clipboard?.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
            class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <span x-show="! copied">{{ __('audit-portal.admin.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('audit-portal.admin.copied') }}</span>
        </button>
    </div>

    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
        {{ __('audit-portal.admin.link_once') }}
    </p>
</div>
