@extends('layouts.auth')

@section('title', 'Reset password')

@section('auth-content')
    <section class="card-accent-edge p-6 shadow-sm sm:p-8" aria-labelledby="reset-password-heading">
        <p class="eyebrow">Account recovery</p>
        <h1 id="reset-password-heading" class="mt-2 text-3xl font-[650] tracking-tight text-ink">Reset password</h1>
        <p class="mt-3 leading-7 text-ink-muted">Choose a new password for your account.</p>

        <x-form-errors :errors="$errors" class="mt-6" />

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5" data-pending>
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <x-form-field
                name="email"
                label="Email"
                type="email"
                :value="request()->input('email')"
                :autocomplete="'username'"
                inputmode="email"
                :maxlength="255"
                required
            />

            <x-form-field
                name="password"
                label="New password"
                type="password"
                :autocomplete="'new-password'"
                hint="Use at least 12 characters. A short phrase of a few words works well."
                required
                autofocus
            />

            <x-form-field
                name="password_confirmation"
                label="Confirm new password"
                type="password"
                :autocomplete="'new-password'"
                hint="Type the same password again."
                required
            />

            <x-btn type="submit" variant="primary" size="lg" block data-pending-button>
                <span data-pending-text>Reset password</span>
            </x-btn>
        </form>
    </section>
@endsection
