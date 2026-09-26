@extends('layouts.auth')

@section('title', 'Create student account')
@section('description', 'Create a student account for IT Learning Hub.')

@section('auth-switch')
    Already registered? <a href="{{ route('login') }}" class="link">Sign in</a>
@endsection

@section('auth-content')
    <h1 class="text-2xl font-[650] tracking-tight text-ink">
        Create student account
    </h1>
    <p class="mt-2 text-sm leading-6 text-ink-muted">
        Instructor and Administrator access is assigned by an Administrator.
    </p>

    <x-form-errors :errors="$errors" class="mt-6" />

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5" data-pending>
        @csrf

        <div class="field flex min-w-0 flex-col">
            <label class="field-label" for="name">Full name</label>
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

        <x-btn type="submit" variant="primary" size="lg" block data-pending-button>
            <span data-pending-text>Create student account</span>
        </x-btn>

        <x-consent class="mt-5 text-center" />
    </form>
@endsection
