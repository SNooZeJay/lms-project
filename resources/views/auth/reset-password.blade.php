@extends('layouts.auth')

@section('title', 'Reset password')

@section('auth-content')
    <section class="border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10" aria-labelledby="reset-password-heading">
        <p class="font-mono text-sm font-semibold text-primary-text">Account recovery</p>
        <h1 id="reset-password-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Reset password</h1>
        <p class="mt-3 leading-7 text-ink-muted">Choose a new password for your account.</p>

        <x-form-errors :errors="$errors" />

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <div>
                <label for="field-email" class="block text-sm font-semibold text-ink">Email</label>
                <input id="field-email" name="email" type="email" value="{{ old('email', request()->input('email')) }}" required autocomplete="username" inputmode="email" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                @error('email')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="field-password" class="block text-sm font-semibold text-ink">New password</label>
                <input id="field-password" name="password" type="password" required autofocus autocomplete="new-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                <p class="mt-2 text-sm text-ink-muted">Use at least 12 characters.</p>
                @error('password')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="field-password-confirmation" class="block text-sm font-semibold text-ink">Confirm new password</label>
                <input id="field-password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>

            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                Reset password
            </button>
        </form>
    </section>
@endsection
