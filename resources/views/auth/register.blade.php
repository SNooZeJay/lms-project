@extends('layouts.auth')

@section('title', 'Create student account')

@section('auth-content')
    <section class="border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10" aria-labelledby="register-heading">
        <p class="font-mono text-sm font-semibold text-primary-text">Begin your learning account</p>
        <h1 id="register-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Create student account</h1>
        <p class="mt-3 leading-7 text-ink-muted">Use your email address to create a learner account. Administrator access is assigned separately.</p>

        <x-form-errors :errors="$errors" />

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="field-name" class="block text-sm font-semibold text-ink">Full name</label>
                <input id="field-name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="255" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                @error('name')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="field-email" class="block text-sm font-semibold text-ink">Email</label>
                <input id="field-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" inputmode="email" maxlength="255" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                @error('email')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="field-password" class="block text-sm font-semibold text-ink">Password</label>
                <input id="field-password" name="password" type="password" required autocomplete="new-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                <p class="mt-2 text-sm text-ink-muted">Use at least 12 characters.</p>
                @error('password')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="field-password-confirmation" class="block text-sm font-semibold text-ink">Confirm password</label>
                <input id="field-password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>

            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                Create student account
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-primary-text underline underline-offset-4 hover:decoration-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Sign in</a>
        </p>
    </section>
@endsection
