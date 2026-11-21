<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow, noarchive">

    <title>{{ __('audit-portal.unavailable.title') }}</title>

    @vite('resources/css/audit-portal.css')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ $organization }}</p>
            <h1 class="mt-3 text-xl font-semibold tracking-tight">{{ __('audit-portal.unavailable.heading') }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">{{ __('audit-portal.unavailable.body') }}</p>
            <p class="mt-6 text-xs leading-5 text-slate-400">{{ __('audit-portal.unavailable.contact') }}</p>
        </div>
    </main>
</body>
</html>
