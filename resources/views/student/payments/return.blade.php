@extends('layouts.app')

@section('title', 'Payment status')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to my courses</a>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        <header class="mt-6">
            <p class="font-mono text-sm font-semibold text-primary-text">Checkout</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $course->title }}</h1>
        </header>

        <section class="mt-6 border-l-4 {{ $paid ? 'border-accent bg-success-surface' : 'border-line bg-surface-muted' }} px-4 py-4" aria-labelledby="payment-state-heading">
            @if ($paid)
                <h2 id="payment-state-heading" class="text-lg font-semibold text-ink">Payment confirmed</h2>
                <p class="mt-1 text-sm leading-6 text-ink-muted">The payment provider confirmed this payment and your enrollment is active.</p>
                <a href="{{ route('student.courses.show', $course) }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open course</a>
            @elseif ($failed)
                <h2 id="payment-state-heading" class="text-lg font-semibold text-ink">Payment did not go through</h2>
                <p class="mt-1 text-sm leading-6 text-ink-muted">Your enrollment is still pending, so you can try again.</p>
            @else
                <h2 id="payment-state-heading" class="text-lg font-semibold text-ink">Waiting for payment confirmation</h2>
                <p class="mt-1 text-sm leading-6 text-ink-muted">
                    This page does not confirm payment by itself. Your enrollment becomes active only after the payment provider sends a verified confirmation.
                </p>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="payment-amount-heading">
            <h2 id="payment-amount-heading" class="text-lg font-semibold text-ink">Amount</h2>
            <dl class="mt-3 grid grid-cols-2 gap-4 border-y border-line py-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-ink-muted">Course price</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $amount['currency'] }} {{ number_format($amount['amount_minor'] / 100, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Your payment</dt>
                    <dd class="mt-1 font-semibold text-ink">
                        @if ($payment)
                            {{ $payment->status->value === 'paid' ? 'Paid' : ucfirst($payment->status->value) }}
                        @else
                            Not started
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Reference</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-ink-muted">{{ $payment?->idempotency_key ?? 'Not created' }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-sm leading-6 text-ink-muted">The amount always comes from the course record. It can never be changed in the browser.</p>
        </section>
    </div>
@endsection
