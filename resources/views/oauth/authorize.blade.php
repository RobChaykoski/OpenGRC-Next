@extends('oauth.layout')

@section('title', __('Authorise :client', ['client' => $client->name]))

@section('content')
    <h1 class="text-xl font-semibold text-gray-900">
        {{ __('Authorise :client', ['client' => $client->name]) }}
    </h1>

    <p class="mt-2 text-sm text-gray-600">
        {{ __('Signed in as :email. This application is asking to act on your behalf.', ['email' => $user->email]) }}
    </p>

    @if (count($scopes) > 0)
        <div class="mt-6">
            <h2 class="text-sm font-medium text-gray-900">
                {{ __('It will be able to:') }}
            </h2>

            <ul class="mt-3 space-y-2">
                @foreach ($scopes as $scope)
                    <li class="text-sm text-gray-700">
                        {{ $scope->description }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <button
                type="submit"
                class="rounded-md bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2"
            >
                {{ __('Authorise') }}
            </button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <button
                type="submit"
                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2"
            >
                {{ __('Cancel') }}
            </button>
        </form>
    </div>
@endsection
