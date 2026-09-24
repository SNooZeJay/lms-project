@extends('layouts.app')

@section('title', 'Change your password')

@section('content')
    <div class="mx-auto flex min-h-[32rem] w-full max-w-2xl items-center px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <section class="w-full border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10" aria-labelledby="password-heading">
            <p class="font-mono text-sm font-semibold text-primary-text">Account security</p>
            <h1 id="password-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Change your password</h1>
            <p class="mt-3 leading-7 text-ink-muted">Use a new password before opening the rest of your account.</p>

            @if (session('status'))
                <div role="status" class="mb-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">
                    {{ session('status') }}
                </div>
            @endif

            <x-form-errors :errors="$errors" />

            <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="field-current-password" class="block text-sm font-semibold text-ink">Current password</label>
                    <input id="field-current-password" name="current_password" type="password" required autofocus autocomplete="current-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    @error('current_password')
                        <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-new-password" class="block text-sm font-semibold text-ink">New password</label>
                    <input id="field-new-password" name="password" type="password" required autocomplete="new-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <p class="mt-2 text-sm text-ink-muted">Use at least 12 characters.</p>
                    @error('password')
                        <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-new-password-confirmation" class="block text-sm font-semibold text-ink">Confirm new password</label>
                    <input id="field-new-password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                        Change password
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                    Sign out
                </button>
            </form>
        </section>
    </div>
@endsection
