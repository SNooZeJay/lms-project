@extends('layouts.auth')

@section('title', 'Sign in')
@section('description', 'Sign in to IT Learning Hub to reach your courses, lessons, and results.')

@section('auth-switch')
    New here? <a href="{{ route('register') }}" class="link">Create a student account</a>
@endsection

@section('auth-content')
    <h1 class="text-2xl font-[650] tracking-tight text-ink">
        Sign in
    </h1>
    <p class="mt-2 text-sm leading-6 text-ink-muted">
        Use the email address your account was created with.
    </p>

    <x-form-errors :errors="$errors" class="mt-6" />

    @if (session('status'))
        <x-note tone="success" class="mt-6">
            {{ session('status') }}
        </x-note>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" data-pending>
        @csrf

        <div class="field flex min-w-0 flex-col">
            <label class="field-label" for="email">Email</label>
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
                    placeholder="you@ncst.edu.ph"
                    autocomplete="username"
                    inputmode="email"
                    maxlength="255"
                    aria-describedby="email-hint"
                    required
                    autofocus
                >
            </div>
            <p id="email-hint" class="field-hint">Use your institution account.</p>
        </div>

        <div class="flex items-center justify-between gap-4">
            <label class="field-label" for="password">Password</label>
            <a href="{{ route('password.request') }}" class="link-quiet text-sm">Forgot password?</a>
        </div>

        <x-password-field name="password" id="password" autocomplete="current-password" />

        <label class="check">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span class="check-box" aria-hidden="true">
                <x-icon name="check" size="xs" />
            </span>
            Keep me signed in
        </label>

        <x-btn type="submit" variant="primary" size="lg" block data-pending-button>
            <span data-pending-text>Sign in</span>
        </x-btn>

        <x-consent class="mt-5 text-center" />
    </form>
@endsection
