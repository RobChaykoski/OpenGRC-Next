<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-lg flex-col justify-center px-6 py-12">
        <p class="mb-6 text-sm font-semibold tracking-wide text-slate-700">
            {{ config('app.name') }}
        </p>

        @yield('content')

        <p class="mt-8 text-xs text-gray-500">
            Only continue if you started this request. You can revoke access later from your profile.
        </p>
    </main>
</body>
</html>
