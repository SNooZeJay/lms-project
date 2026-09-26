@extends('layouts.app-shell')

@section('title', 'Payment status')
@section('workspace-context', 'Checkout')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'My courses', 'href' => route('student.courses.index')],
            ['label' => 'Payment status'],
        ]" class="mb-6" />

        @if (session('status'))
            <x-note tone="success" class="mb-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mb-6" />

        <header class="border-b border-line pb-8">
            <p class="eyebrow">Checkout</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                {{ $course->title }}
            </h1>
        </header>

        {{-- The payment state, written in words. A Student is never misled into
             thinking a browser return confirmed anything. --}}
        <section class="mt-8" aria-labelledby="payment-state-heading">
            <div class="card p-5">
                @if ($paid)
                    <div class="flex items-start gap-3">
                        <x-icon name="check-circle" size="lg" class="mt-0.5 shrink-0 text-success-text" />
                        <div>
                            <h2 id="payment-state-heading" class="text-xl font-semibold text-ink">Payment confirmed</h2>
                            <p class="mt-1 text-sm leading-6 text-ink-muted">
                                The payment provider confirmed this payment and your enrollment is active.
                            </p>
                        </div>
                    </div>

                    <x-btn :href="route('student.courses.show', $course)" variant="primary" size="lg" class="mt-5">
                        <x-icon name="book-open" size="sm" />
                        Open course
                    </x-btn>
                @elseif ($failed)
                    <div class="flex items-start gap-3">
                        <x-icon name="x-circle" size="lg" class="mt-0.5 shrink-0 text-error-text" />
                        <div>
                            <h2 id="payment-state-heading" class="text-xl font-semibold text-ink">
                                Payment did not go through
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-ink-muted">
                                Nothing was charged, and your enrollment is still waiting. You can start the payment
                                again.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-3">
                        <x-icon name="clock" size="lg" class="mt-0.5 shrink-0 text-warning-text" />
                        <div>
                            <h2 id="payment-state-heading" class="text-xl font-semibold text-ink">
                                Waiting for payment confirmation
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-ink-muted">
                                This page does not confirm payment by itself. Your enrollment becomes active only
                                after the payment provider sends a verified confirmation to the server.
                            </p>
                            <p class="mt-3 text-sm leading-6 text-ink-muted" data-payment-poll>
                                This page checks for you every few seconds. You can also reload it.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- The amount, always read from the course record. --}}
        <section class="mt-6" aria-labelledby="payment-amount-heading">
            <h2 id="payment-amount-heading" class="text-lg font-semibold text-ink">Amount</h2>

            <dl class="card mt-3 grid grid-cols-1 gap-4 p-5 text-sm sm:grid-cols-3">
                <div>
                    <dt class="meta-label">Course price</dt>
                    <dd class="mt-1 font-semibold text-ink">
                        <x-amount
                            :minor="$amount['amount_minor']"
                            :currency="$amount['currency']"
                            :code="true"
                        />
                    </dd>
                </div>
                <div>
                    <dt class="meta-label">Your payment</dt>
                    <dd class="mt-1">
                        @if ($payment)
                            <x-status :value="$payment->status->value" />
                        @else
                            <x-badge tone="neutral">Not started</x-badge>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="meta-label">Reference</dt>
                    <dd class="mt-1 font-mono text-xs break-all text-ink">
                        {{ $payment?->idempotency_key ?? 'Not created' }}
                    </dd>
                </div>
            </dl>

            <x-note tone="info" class="mt-3">
                Quote the reference above if you need to ask for help.
            </x-note>
        </section>

        @unless ($paid)
            <x-note tone="info" class="mt-6">
                This page cannot report which button was pressed, so it waits for the provider's signed event and
                updates itself. On the test page, choose
                <span class="font-semibold text-ink">Authorize test payment</span> to complete the payment, or
                <span class="font-semibold text-ink">Fail or expire test payment</span> to see the retry path.
            </x-note>
        @endunless

        <div class="mt-8 border-t border-line pt-6">
            <x-btn :href="route('student.courses.index')" variant="secondary" size="lg">
                <x-icon name="arrow-left" size="sm" />
                Back to my courses
            </x-btn>
        </div>
    </div>
@endsection

@push('scripts')
    @unless ($paid)
        <script nonce="{{ $cspNonce ?? '' }}">
            // The provider confirms by webhook, not by the browser coming back,
            // so the page reloads itself until the state changes. A student
            // should not have to guess whether anything happened.
            (() => {
                const marker = document.querySelector('[data-payment-poll]');

                if (! marker) {
                    return;
                }

                window.setTimeout(() => window.location.reload(), 5000);
            })();
        </script>
    @endunless
@endpush
