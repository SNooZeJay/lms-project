@extends('layouts.app')

@section('title', 'Privacy')
@section('description', 'What personal data IT Learning Hub stores, why, and who can see it.')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <x-page-header
            title="Privacy"
            description="What is stored, why it is stored, and who can read it."
        />

        <div class="mt-10 space-y-8 text-[0.9375rem] leading-7 text-ink-muted">
            <section>
                <h2 class="text-base font-semibold text-ink">What is collected</h2>
                <ul role="list" class="mt-3 space-y-2">
                    <li class="flex gap-2">
                        <x-icon name="check" size="sm" class="mt-1 shrink-0 text-accent" />
                        <span>Your name, email address, and an optional short biography.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="check" size="sm" class="mt-1 shrink-0 text-accent" />
                        <span>Your role and whether your account is active.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="check" size="sm" class="mt-1 shrink-0 text-accent" />
                        <span>Enrollment, lesson progress, and quiz results.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="check" size="sm" class="mt-1 shrink-0 text-accent" />
                        <span>Payment status, amount, and provider reference for a paid course.</span>
                    </li>
                    <li class="flex gap-2">
                        <x-icon name="check" size="sm" class="mt-1 shrink-0 text-accent" />
                        <span>An activity log of administrative actions, such as a role change.</span>
                    </li>
                </ul>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Why it is collected</h2>
                <p class="mt-2">
                    Your name and email identify your account and let you sign in. Progress and
                    results exist so you can see where you stand and so a certificate can be
                    issued against real records. The activity log exists so an Administrator can
                    see who changed a role or revoked a certificate.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Who can see it</h2>
                <p class="mt-2">
                    A student sees only their own records. An Instructor sees enrollment and
                    progress for their own courses. An Administrator sees accounts, payments, and
                    the activity log. Every one of those checks runs on the server, so changing
                    the page in your browser does not widen what you can see.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Passwords</h2>
                <p class="mt-2">
                    Passwords are hashed before they are stored. The plain text is never written
                    to the database, a log, or an error page, and it cannot be recovered, only
                    replaced.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">The payment provider</h2>
                <p class="mt-2">
                    If you pay for a course, PayMongo receives your name, email, and the amount,
                    and sends the result back to this site. This application stores the provider's
                    reference and the payment status, not your full card details, because card
                    details are never collected here.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Email and cookies</h2>
                <p class="mt-2">
                    Verification and password reset messages are sent to your email address. In
                    local development, mail is written to the application log instead of being
                    sent. The site stores a session cookie and a CSRF cookie, both marked secure
                    and both required for the site to work. Your light or dark theme choice is
                    kept in your own browser, not on the server.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Changing or removing your data</h2>
                <p class="mt-2">
                    You can correct your name, email, and biography from your account page, and
                    change your password at any time. Because enrollment, progress, and
                    certificate records are kept for academic integrity, they are not deleted on
                    request; an Administrator can correct them. This is a coursework system with
                    no automated retention job.
                </p>
            </section>
        </div>

        <p class="mt-12 border-t border-line pt-6 text-sm text-ink-subtle">
            Last reviewed {{ now()->format('F Y') }}.
        </p>
    </div>
@endsection
