@if ($views->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('audit-portal.admin.views_empty') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="py-2 pr-4 font-medium">{{ __('audit-portal.admin.viewed_at') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('audit-portal.admin.ip') }}</th>
                    <th class="py-2 font-medium">{{ __('audit-portal.admin.user_agent') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/10 dark:text-gray-200">
                @foreach ($views as $view)
                    <tr>
                        <td class="py-2 pr-4 whitespace-nowrap">{{ $view->viewed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="py-2 pr-4 font-mono text-xs">{{ $view->ip ?? '—' }}</td>
                        <td class="py-2 text-xs">{{ \Illuminate\Support\Str::limit((string) $view->user_agent, 80) ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
