@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <div class="mx-auto flex min-h-[32rem] w-full max-w-2xl items-center px-4 py-16 sm:px-6 lg:px-8">
        <section class="w-full border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10" aria-labelledby="verify-email-heading">
            <p class="font-mono text-sm font-semibold text-primary-text">Account verification</p>
            <h1 id="verify-email-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Verify your email</h1>
            <p class="mt-4 leading-7 text-ink-muted">We sent a verification link to your email address. Open the link before continuing to your account.</p>
            <p class="mt-3 text-sm text-ink-muted">The local demo mailer writes the message to the Laravel log.</p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                        Resend verification email
                    </button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                        Sign out
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
