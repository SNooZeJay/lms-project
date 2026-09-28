@extends('layouts.auth')

@section('title', 'Verify your email')

@section('auth-content')
    <section class="card-accent-edge p-6 shadow-sm sm:p-8" aria-labelledby="verify-email-heading">
        <p class="eyebrow">Account verification</p>
        <h1 id="verify-email-heading" class="mt-2 text-3xl font-[650] tracking-tight text-ink">Verify your email</h1>
        <p class="mt-3 leading-7 text-ink-muted">
            We sent a verification link to your email address. Open the link before continuing to your account.
        </p>

        <x-note tone="info" class="mt-5">
            Delivery can take a minute. Check your spam folder if the message has not
            arrived, then resend it.
        </x-note>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-btn type="submit" variant="primary" size="lg" block>
                    Resend verification email
                </x-btn>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-btn type="submit" variant="secondary" size="lg" block>
                    Sign out
                </x-btn>
            </form>
        </div>
    </section>
@endsection
