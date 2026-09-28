@extends('layouts.auth')

@section('title', 'Sign in')
@section('description', 'Sign in to IT Learning Hub to reach your courses, lessons, and results.')

@section('auth-switch')
    New here? <a href="{{ route('register') }}" class="link tap">Create account</a>
@endsection

@section('auth-consent')
    <x-consent />
@endsection

@section('auth-content')
    <h1 class="text-2xl font-[650] tracking-tight text-ink">
        Welcome back
    </h1>
    <p class="mt-2 text-sm leading-6 text-ink-muted">
        Sign in to continue learning.
    </p>

    <x-form-errors :errors="$errors" class="mt-6" />

    @if (session('status'))
        <x-note tone="success" class="mt-6">
            {{ session('status') }}
        </x-note>
    @endif

    {{-- The gap between fields is the same everywhere, so the two forms feel
         like one product even though one has twice the fields. --}}
    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-3.5" data-pending>
        @csrf

        <div class="field flex min-w-0 flex-col">
            <label class="field-label" for="email">Email address</label>
            <div class="input-icon">
                <span class="input-icon-mark" aria-hidden="true">
                    <x-icon name="mail" size="sm" />
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    class="field-control"
                    placeholder="you@example.com"
                    autocomplete="username"
                    inputmode="email"
                    maxlength="255"
                    required
                    autofocus
                >
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-4">
                <label class="field-label" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="link-quiet tap text-sm">Forgot password?</a>
            </div>

            <x-password-field
                name="password"
                id="password"
                autocomplete="current-password"
                class="mt-2"
            />
        </div>

        <div class="pt-1">
            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span class="check-box" aria-hidden="true">
                    <x-icon name="check" size="xs" />
                </span>
                Keep me signed in
            </label>
        </div>

        <x-btn type="submit" variant="primary" size="lg" block class="mt-2" data-pending-button>
            <span data-pending-text>Sign in</span>
        </x-btn>
    </form>
@endsection
