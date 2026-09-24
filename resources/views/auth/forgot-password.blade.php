@extends('layouts.auth')

@section('title', 'Forgot password')

@section('auth-content')
    <section class="border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10" aria-labelledby="forgot-password-heading">
        <p class="font-mono text-sm font-semibold text-primary-text">Account recovery</p>
        <h1 id="forgot-password-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Forgot password</h1>
        <p class="mt-3 leading-7 text-ink-muted">Enter your email address. If an account exists, we will send a reset link.</p>

        <x-form-errors :errors="$errors" />

        @if (session('status'))
            <div role="status" class="mb-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="field-email" class="block text-sm font-semibold text-ink">Email</label>
                <input id="field-email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                @error('email')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                Email reset link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            <a href="{{ route('login') }}" class="font-semibold text-primary-text underline underline-offset-4 hover:decoration-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Return to sign in</a>
        </p>
    </section>
@endsection
