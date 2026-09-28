@extends('layouts.auth')

@section('title', 'Confirm your password')

@section('auth-content')
    <section class="card-accent-edge p-6 shadow-sm sm:p-8" aria-labelledby="confirm-password-heading">
        <p class="eyebrow">Security check</p>
        <h1 id="confirm-password-heading" class="mt-2 text-3xl font-[650] tracking-tight text-ink">Confirm your password</h1>
        <p class="mt-3 leading-7 text-ink-muted">
            This is a sensitive part of your account. Enter your password again to continue.
        </p>

        <x-note tone="info" class="mt-4">
            You will be returned to the page you were trying to reach.
        </x-note>

        <x-form-errors :errors="$errors" class="mt-6" />

        <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5" data-pending>
            @csrf

            <x-form-field
                name="password"
                label="Password"
                type="password"
                :autocomplete="'current-password'"
                hint="Your current account password, not a new one."
                required
                autofocus
            />

            <x-btn type="submit" variant="primary" size="lg" block data-pending-button>
                <span data-pending-text>Confirm</span>
            </x-btn>
        </form>
    </section>
@endsection
