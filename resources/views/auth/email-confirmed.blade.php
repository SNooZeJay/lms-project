@extends('layouts.auth')

@section('title', 'Address confirmed')

@section('auth-content')
    {{--
        What somebody sees at the end of verifying an address.

        This page exists because the confirmation had nowhere to go. Following the
        link in the email marked the address verified and then sent the person to
        /account/profile, silently. The account was right, the redirect was right,
        and nothing at all acknowledged the click. The only account page reachable
        without verifying is this one, so before there was a confirmation to read
        here, the one moment the product had somebody's attention was spent showing
        them a form.

        It is a separate address rather than a second state of /email/verify on
        purpose. That page belongs to an account that has not been verified, and
        Fortify answers a visit from somebody who has with a redirect away from it,
        so a confirmed state placed there could never be rendered. A new address
        cannot collide with that behaviour, and arriving at /email/confirmed says
        what happened before a word is read.
    --}}
    <section class="card-accent-edge p-6 shadow-sm sm:p-8" aria-labelledby="confirmed-heading">
        <p class="eyebrow">Account verification</p>

        <div class="mt-2 flex items-start gap-3">
            {{--
                The tick is decorative and hidden from assistive technology. The
                heading beside it is the message, and a control that is announced
                twice is worse than one that is announced once.
            --}}
            <span
                class="mt-1 flex size-9 shrink-0 items-center justify-center rounded-full bg-success-surface text-success-text"
                aria-hidden="true"
            >
                <x-icon name="check-circle" size="md" />
            </span>
            <h1 id="confirmed-heading" class="text-3xl font-[650] tracking-tight text-ink">
                Your address is confirmed
            </h1>
        </div>

        <p class="mt-3 leading-7 text-ink-muted">
            The link in your email worked. This account is ready to use, and nothing else is
            needed to finish setting it up.
        </p>

        <x-note tone="success" class="mt-5">
            You are signed in on this device, so there is no password to type again. Taking
            you through to your workspace now.
        </x-note>

        <div
            class="mt-6"
            data-auto-continue
            data-seconds="8"
            data-href="{{ \App\Support\RoleBasedDestination::for(auth()->user()) }}"
        >
            {{--
                The button is a real link to a real destination, so the page works with
                scripting unavailable. The timer below only removes one click from
                somebody who was already on their way.
            --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <x-btn
                    :href="\App\Support\RoleBasedDestination::for(auth()->user())"
                    variant="primary"
                    size="lg"
                    block
                    data-auto-continue-now
                >
                    Continue to my workspace
                </x-btn>

                <x-btn
                    :href="\App\Support\RoleBasedDestination::for(auth()->user())"
                    variant="secondary"
                    size="lg"
                    block
                    data-auto-continue-cancel
                    hidden
                >
                    Stay here
                </x-btn>
            </div>

            {{--
                A polite live region, so the number is announced as it changes rather
                than the page simply moving on its own. Polite and not assertive: a
                page changing by itself is disorienting but it is not an emergency,
                and it should not interrupt whatever is being read.
            --}}
            <p
                class="mt-4 text-sm text-ink-muted"
                role="status"
                aria-live="polite"
                data-auto-continue-status
            >
                Continuing in <span data-auto-continue-count>8</span> seconds.
            </p>

            <noscript>
                <p class="mt-2 text-sm text-ink-muted">
                    The automatic step needs JavaScript. The button above goes straight there.
                </p>
            </noscript>
        </div>
    </section>
@endsection
