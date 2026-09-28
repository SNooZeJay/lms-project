@extends('layouts.auth')

@section('title', 'Create your account')
@section('description', 'Create an account for IT Learning Hub.')

@section('auth-switch')
    Already have an account? <a href="{{ route('login') }}" class="link tap">Sign in</a>
@endsection

@section('auth-consent')
    <x-consent action="creating an account" />
@endsection

@section('auth-content')
    <h1 class="text-2xl font-[650] tracking-tight text-ink">
        Create your account
    </h1>
    <p class="mt-2 text-sm leading-6 text-ink-muted">
        Start learning with IT Learning Hub.
    </p>

    <x-form-errors :errors="$errors" class="mt-6" />

    {{-- The same field gap as the sign in page, so the longer form reads as the
         same product rather than a denser one. --}}
    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-3.5" data-pending>
        @csrf

        <div class="field flex min-w-0 flex-col">
            <label class="field-label" for="name">Full name</label>
            <div class="input-icon">
                <span class="input-icon-mark" aria-hidden="true">
                    <x-icon name="user" size="sm" />
                </span>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    class="field-control"
                    placeholder="Juan Dela Cruz"
                    autocomplete="name"
                    maxlength="255"
                    required
                    autofocus
                >
            </div>
        </div>

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
                >
            </div>
        </div>

        <x-password-field
            name="password"
            label="Password"
            autocomplete="new-password"
            hint="At least 12 characters. A short phrase of a few words works well."
        />

        <x-password-field
            name="password_confirmation"
            label="Confirm password"
            autocomplete="new-password"
        />

        <x-btn type="submit" variant="primary" size="lg" block class="mt-2" data-pending-button>
            <span data-pending-text>Create account</span>
        </x-btn>
    </form>
@endsection
