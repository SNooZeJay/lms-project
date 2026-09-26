@extends('layouts.auth')

@section('title', 'Forgot password')

@section('auth-content')
    <section class="card-accent-edge p-6 shadow-sm sm:p-8" aria-labelledby="forgot-password-heading">
        <p class="eyebrow">Account recovery</p>
        <h1 id="forgot-password-heading" class="mt-2 text-3xl font-[650] tracking-tight text-ink">Forgot password</h1>
        <p class="mt-3 leading-7 text-ink-muted">
            Enter your email address. If an account exists, we will send a reset link.
        </p>

        <x-note tone="info" class="mt-4">
            For your safety, the response is the same whether or not the address is registered.
        </x-note>

        <x-form-errors :errors="$errors" class="mt-6" />

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5" data-pending>
            @csrf

            <x-form-field
                name="email"
                label="Email"
                type="email"
                :autocomplete="'email'"
                inputmode="email"
                :maxlength="255"
                placeholder="juan.delacruz@ncst.edu.ph"
                required
                autofocus
            />

            <x-btn type="submit" variant="primary" size="lg" block data-pending-button>
                <span data-pending-text>Email reset link</span>
            </x-btn>
        </form>

        <p class="mt-6 border-t border-line pt-6 text-center text-sm text-ink-muted">
            Remembered it?
            <a href="{{ route('login') }}" class="link">Return to sign in</a>
        </p>
    </section>
@endsection
