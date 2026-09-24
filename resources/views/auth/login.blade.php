@extends('layouts.auth')

@section('title', 'Sign in')

@section('auth-content')
    <section class="border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10" aria-labelledby="login-heading">
        <p class="font-mono text-sm font-semibold text-primary-text">Welcome back</p>
        <h1 id="login-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Sign in</h1>
        <p class="mt-3 leading-7 text-ink-muted">Continue to your IT Learning Hub account.</p>

        <x-form-errors :errors="$errors" />

        @if (session('status'))
            <div role="status" class="mb-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="field-email" class="block text-sm font-semibold text-ink">Email</label>
                <input id="field-email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                @error('email')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-center justify-between gap-4">
                    <label for="field-password" class="block text-sm font-semibold text-ink">Password</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-primary-text underline underline-offset-4 hover:decoration-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Forgot password?</a>
                </div>
                <input id="field-password" name="password" type="password" required autocomplete="current-password" class="mt-2 min-h-11 w-full rounded-md border border-line bg-canvas px-3 py-2 text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                @error('password')
                    <p class="mt-2 text-sm text-error-text" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <label for="remember" class="flex min-h-11 items-center gap-3 text-sm text-ink-muted">
                <input id="remember" name="remember" type="checkbox" class="h-5 w-5 rounded border-line text-primary focus:ring-3 focus:ring-focus" @checked(old('remember'))>
                Remember me on this device
            </label>

            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                Sign in
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            New to IT Learning Hub?
            <a href="{{ route('register') }}" class="font-semibold text-primary-text underline underline-offset-4 hover:decoration-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Create a student account</a>
        </p>
    </section>
@endsection
