@extends('oauth.layout')

@section('title', __('Connect a device'))

@section('content')
    <h1 class="text-xl font-semibold text-gray-900">
        {{ __('Connect a device') }}
    </h1>

    <p class="mt-2 text-sm text-gray-600">
        {{ __('Enter the code shown on the device you are connecting to your account.') }}
    </p>

    <form method="GET" action="{{ route('passport.device') }}" class="mt-6">
        <label for="user_code" class="block text-sm font-medium text-gray-700">
            {{ __('Device code') }}
        </label>

        <input
            id="user_code"
            name="user_code"
            type="text"
            value="{{ old('user_code', $request->query('user_code')) }}"
            autocomplete="one-time-code"
            autocapitalize="characters"
            spellcheck="false"
            required
            aria-describedby="user_code_help"
            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm uppercase tracking-widest text-gray-900 focus:border-slate-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
        >

        @error('user_code')
            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <p id="user_code_help" class="mt-2 text-xs text-gray-500">
            {{ __('The code is usually shown on the device or in the application requesting access.') }}
        </p>

        <button
            type="submit"
            class="mt-6 rounded-md bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2"
        >
            {{ __('Continue') }}
        </button>
    </form>
@endsection
