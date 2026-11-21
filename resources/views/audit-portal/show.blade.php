@php
    $auditTypeLabels = [
        'standards' => __('audit-portal.audit_type.standards'),
        'implementations' => __('audit-portal.audit_type.implementations'),
        'program' => __('audit-portal.audit_type.program'),
    ];

    $toneClasses = [
        'good' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'warn' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'bad' => 'bg-red-50 text-red-700 ring-red-600/20',
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    ];

    $effectivenessTone = fn (?string $label): string => match ($label) {
        __('enums.effectiveness.effective') => 'good',
        __('enums.effectiveness.partial') => 'warn',
        __('enums.effectiveness.ineffective') => 'bad',
        default => 'neutral',
    };

    $validationTone = fn (?string $state): string => match ($state) {
        'effective' => 'good',
        'failed' => 'bad',
        default => 'neutral',
    };

    $formatDate = fn ($value): string => $value
        ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d')
        : '—';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow, noarchive">

    <title>{{ $audit->title }} · {{ __('audit-portal.title') }}</title>

    @vite('resources/css/audit-portal.css')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div>
                <p class="text-sm font-medium text-slate-500">{{ $organization }}</p>
                <p class="text-lg font-semibold tracking-tight">{{ __('audit-portal.title') }}</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ __('audit-portal.read_only') }}
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $audit->title }}</h1>
                    @if ($audit->description)
                        <p class="max-w-3xl text-sm leading-6 text-slate-600">{{ $audit->description }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium ring-1 ring-inset ring-slate-500/20">
                            {{ $auditTypeLabels[$audit->audit_type] ?? ucfirst((string) $audit->audit_type) }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium ring-1 ring-inset ring-slate-500/20">
                            {{ $audit->status?->getLabel() ?? (string) $audit->status }}
                        </span>
                    </div>
                </div>

                <dl class="grid shrink-0 grid-cols-2 gap-x-8 gap-y-3 text-sm lg:text-right">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('audit-portal.meta.period') }}</dt>
                        <dd class="mt-0.5 text-slate-800">
                            {{ $formatDate($audit->start_date) }}
                            <span class="text-slate-400">→</span>
                            {{ $formatDate($audit->end_date) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('audit-portal.meta.manager') }}</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $audit->manager?->name ?? __('audit-portal.meta.unassigned') }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('audit-portal.meta.generated') }}</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $generated_at->format('Y-m-d H:i T') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-5 flex flex-col gap-2 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-inset ring-slate-200 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    <span class="font-medium text-slate-800">{{ $grant->label }}</span>
                    · {{ __('audit-portal.meta.expires', ['date' => $grant->expires_at->format('Y-m-d H:i T')]) }}
                </p>
                <p class="text-xs">{{ __('audit-portal.meta.logged') }}</p>
            </div>
        </section>

        <section aria-label="{{ __('audit-portal.summary.heading') }}" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['label' => __('audit-portal.summary.items'), 'value' => $summary['items'], 'tone' => 'neutral'],
                ['label' => __('audit-portal.summary.completed'), 'value' => $summary['completed'], 'tone' => 'neutral'],
                ['label' => __('audit-portal.summary.effective'), 'value' => $summary['effective'], 'tone' => 'good'],
                ['label' => __('audit-portal.summary.partial'), 'value' => $summary['partial'], 'tone' => 'warn'],
                ['label' => __('audit-portal.summary.ineffective'), 'value' => $summary['ineffective'], 'tone' => 'bad'],
                ['label' => __('audit-portal.summary.findings'), 'value' => $summary['open_findings'], 'tone' => $summary['open_findings'] > 0 ? 'warn' : 'neutral'],
            ] as $card)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums {{ $card['tone'] === 'bad' ? 'text-red-600' : ($card['tone'] === 'warn' ? 'text-amber-700' : 'text-slate-900') }}">
                        {{ $card['value'] }}
                    </p>
                </div>
            @endforeach
        </section>

        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 class="text-base font-semibold tracking-tight">{{ __('audit-portal.items.heading') }}</h2>
                <p class="text-xs text-slate-500">{{ __('audit-portal.items.evidence_count', ['count' => $summary['evidence_records']]) }}</p>
            </div>

            @forelse ($items as $item)
                <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-700">
                                    {{ $item['code'] ?? __('audit-portal.items.unknown_code') }}
                                </span>
                                <span class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                    {{ $item['type'] === 'implementation' ? __('audit-portal.items.implementation') : __('audit-portal.items.control') }}
                                </span>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $item['title'] ?? __('audit-portal.items.untitled') }}</h3>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full px-2.5 py-1 font-medium ring-1 ring-inset {{ $toneClasses[$effectivenessTone($item['effectiveness'])] }}">
                                {{ $item['effectiveness'] }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">
                                {{ $item['status'] }}
                            </span>
                            <span class="rounded-full bg-slate-50 px-2.5 py-1 font-medium text-slate-500 ring-1 ring-inset ring-slate-200">
                                {{ $item['applicability'] }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-5 px-5 py-4">
                        @if ($item['details'])
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('audit-portal.items.implementation_details') }}</h4>
                                <div class="prose prose-sm mt-2 max-w-none text-slate-700">
                                    @safeHtml($item['details'])
                                </div>
                            </div>
                        @endif

                        @if ($item['last_tested_at'] || $item['next_test_at'])
                            <dl class="flex flex-wrap gap-x-8 gap-y-2 text-xs text-slate-600">
                                <div>
                                    <dt class="font-medium text-slate-500">{{ __('audit-portal.items.last_tested') }}</dt>
                                    <dd>{{ $item['last_tested_at']?->format('Y-m-d') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">{{ __('audit-portal.items.next_test') }}</dt>
                                    <dd>{{ $item['next_test_at']?->format('Y-m-d') ?? '—' }}</dd>
                                </div>
                            </dl>
                        @endif

                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('audit-portal.items.auditor_notes') }}</h4>
                            @if ($item['notes'])
                                <div class="prose prose-sm mt-2 max-w-none text-slate-700">
                                    @safeHtml($item['notes'])
                                </div>
                            @else
                                <p class="mt-2 text-sm italic text-slate-400">{{ __('audit-portal.items.no_notes') }}</p>
                            @endif
                        </div>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('audit-portal.items.evidence') }}</h4>
                                @if ($item['evidence']->isNotEmpty())
                                    <div class="mt-2 overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="text-slate-500">
                                                <tr>
                                                    <th class="py-1 pr-3 font-medium">{{ __('audit-portal.evidence.source') }}</th>
                                                    <th class="py-1 pr-3 font-medium">{{ __('audit-portal.evidence.type') }}</th>
                                                    <th class="py-1 pr-3 font-medium">{{ __('audit-portal.evidence.status') }}</th>
                                                    <th class="py-1 pr-3 font-medium">{{ __('audit-portal.evidence.collected') }}</th>
                                                    <th class="py-1 font-medium">{{ __('audit-portal.evidence.hash') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                                @foreach ($item['evidence'] as $record)
                                                    <tr>
                                                        <td class="py-1.5 pr-3">{{ $record->source?->name ?? '—' }}</td>
                                                        <td class="py-1.5 pr-3">{{ $record->evidence_type ?? '—' }}</td>
                                                        <td class="py-1.5 pr-3">{{ $record->status?->getLabel() ?? '—' }}</td>
                                                        <td class="py-1.5 pr-3 whitespace-nowrap">{{ $record->collected_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                        <td class="py-1.5 font-mono text-[11px] text-slate-500" title="{{ $record->payload_sha256 }}">
                                                            {{ $record->payload_sha256 ? substr($record->payload_sha256, 0, 12).'…' : '—' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="mt-2 text-sm italic text-slate-400">{{ __('audit-portal.items.no_evidence') }}</p>
                                @endif

                                @if ($item['validations']->isNotEmpty())
                                    <h5 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('audit-portal.items.validations') }}</h5>
                                    <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                        @foreach ($item['validations'] as $validation)
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full px-2 py-0.5 font-medium ring-1 ring-inset {{ $toneClasses[$validationTone($validation->status?->value)] }}">
                                                    {{ $validation->status?->getLabel() ?? '—' }}
                                                </span>
                                                <span class="text-slate-500">{{ $validation->evaluated_at?->format('Y-m-d H:i') ?? '—' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('audit-portal.items.findings') }}</h4>
                                @if ($item['findings']->isNotEmpty())
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($item['findings'] as $finding)
                                            <li class="rounded-lg border border-slate-200 px-3 py-2 text-xs">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="font-mono text-slate-500">{{ $finding->reference() }}</span>
                                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">
                                                        {{ $finding->severity?->getLabel() ?? '—' }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm font-medium text-slate-800">{{ $finding->title }}</p>
                                                <p class="mt-0.5 text-slate-500">
                                                    {{ $finding->status?->getLabel() ?? '—' }}
                                                    @if ($finding->due_at)
                                                        · {{ __('audit-portal.findings.due', ['date' => $finding->due_at->format('Y-m-d')]) }}
                                                    @endif
                                                </p>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-2 text-sm italic text-slate-400">{{ __('audit-portal.items.no_findings') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">{{ __('audit-portal.items.empty_heading') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('audit-portal.items.empty_description') }}</p>
                </div>
            @endforelse
        </section>

        <footer class="space-y-2 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500">
            <p>{{ __('audit-portal.footer.read_only') }}</p>
            <p>{{ __('audit-portal.footer.boundary') }}</p>
            <p>{{ __('audit-portal.footer.hash') }}</p>
        </footer>
    </main>
</body>
</html>
