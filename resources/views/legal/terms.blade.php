@extends('layouts.app')

@section('title', 'Terms of use')
@section('description', 'The terms that apply when you use IT Learning Hub.')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <x-page-header
            title="Terms of use"
            description="What using this site agrees to."
        />

        <div class="mt-10 space-y-8 text-[0.9375rem] leading-7 text-ink-muted">
            <section>
                <h2 class="text-base font-semibold text-ink">What this site is</h2>
                <p class="mt-2">
                    IT Learning Hub is an academic project built for a BSIT course. It is
                    run for coursework and demonstration, not as a commercial service. There
                    is no support desk and no service level agreement.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Your account</h2>
                <p class="mt-2">
                    Registration creates a student account. Instructor and Administrator access
                    is assigned by an Administrator and cannot be self selected. You are
                    responsible for keeping your password to yourself, and you must tell an
                    Administrator if you think someone else has used your account.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Acceptable use</h2>
                <p class="mt-2">
                    Do not attempt to reach another person's records, upload material you do
                    not have the right to share, or interfere with other people's access. The
                    application checks authorization on the server for every protected action,
                    so a modified browser cannot be used to see another student's work.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Payments</h2>
                <p class="mt-2">
                    Paid courses are charged once through PayMongo. A payment is only treated as
                    complete when the provider confirms it, and the course opens at that point.
                    Refunds and chargebacks are handled by the payment provider, not by this
                    application. Prices are stored in minor units with a currency code and are
                    never read from the browser.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Certificates</h2>
                <p class="mt-2">
                    A certificate records your name and the course title as they were on the day
                    it was issued, along with a unique code. It can be revoked by an
                    Administrator, and a revoked certificate stays on record showing that it was
                    revoked rather than disappearing.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">Availability and changes</h2>
                <p class="mt-2">
                    The site runs from a development machine, so it is only reachable while that
                    machine is on and online. Content may be added, changed, or withdrawn. Your
                    enrollment and progress records are kept if a course is later unpublished.
                </p>
            </section>

            <section>
                <h2 class="text-base font-semibold text-ink">No warranty</h2>
                <p class="mt-2">
                    The site is provided as is, for coursework. To the extent the law allows, no
                    warranty is given that it will be uninterrupted, error free, or fit for any
                    particular purpose.
                </p>
            </section>
        </div>

        <p class="mt-12 border-t border-line pt-6 text-sm text-ink-subtle">
            Last reviewed {{ now()->format('F Y') }}.
        </p>
    </div>
@endsection
