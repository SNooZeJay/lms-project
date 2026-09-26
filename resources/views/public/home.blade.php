@extends('layouts.app')

@section('title', 'IT Learning Hub')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-20">
        <section class="grid gap-10 lg:grid-cols-12 lg:gap-12" aria-labelledby="foundation-heading">
            <div class="lg:col-span-7">
                <div class="flex items-center gap-3 text-sm font-semibold text-primary-text">
                    <span class="font-mono">01</span>
                    <span class="h-px w-10 bg-primary" aria-hidden="true"></span>
                    <span>Foundation preview</span>
                </div>

                <h1 id="foundation-heading" class="mt-6 max-w-3xl text-4xl leading-tight font-[650] tracking-tight text-ink sm:text-5xl lg:text-6xl">
                    A clear foundation for academic learning.
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-8 text-ink-muted">
                    A calm, practical place for BSIT learners to build academic momentum through clear course workflows.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                        Browse published courses
                    </a>
                    @auth
                        <a href="{{ route('account.profile') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                            Open your account
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                            Sign in
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                            Create student account
                        </a>
                    @endauth
                </div>

                <div class="mt-8 border-l-4 border-accent bg-surface px-5 py-4 sm:px-6">
                    <p class="text-sm font-semibold text-ink">Current boundary</p>
                    <p class="mt-1 max-w-xl leading-7 text-ink-muted">
                        You can browse published courses, enroll in free ones, and read your lessons. Progress, quizzes, certificates, uploads, and payments remain later work.
                    </p>
                </div>
            </div>

            <aside class="self-start border-t-4 border-primary bg-surface p-6 shadow-sm lg:col-span-5 lg:p-8" aria-labelledby="readiness-heading">
                <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
                    <h2 id="readiness-heading" class="text-lg font-semibold text-ink">Foundation readiness</h2>
                    <span class="inline-flex items-center gap-2 rounded-full bg-success-surface px-3 py-1 text-sm font-semibold text-success-text">
                        <span class="size-2 rounded-full bg-success-text" aria-hidden="true"></span>
                        In place
                    </span>
                </div>

                <ol class="divide-y divide-line">
                    <li class="flex gap-4 py-5">
                        <span class="font-mono text-sm font-semibold text-primary-text">01</span>
                        <div>
                            <p class="font-semibold text-ink">Application shell</p>
                            <p class="mt-1 text-sm leading-6 text-ink-muted">Named routes, a thin controller, Blade layouts, and safe errors.</p>
                        </div>
                    </li>
                    <li class="flex gap-4 py-5">
                        <span class="font-mono text-sm font-semibold text-primary-text">02</span>
                        <div>
                            <p class="font-semibold text-ink">Interface system</p>
                            <p class="mt-1 text-sm leading-6 text-ink-muted">Responsive tokens, visible focus, and a persistent light or dark theme.</p>
                        </div>
                    </li>
                    <li class="flex gap-4 py-5">
                        <span class="font-mono text-sm font-semibold text-primary-text">03</span>
                        <div>
                            <p class="font-semibold text-ink">Data foundation</p>
                            <p class="mt-1 text-sm leading-6 text-ink-muted">Dedicated MySQL databases with framework infrastructure and approved identity tables.</p>
                        </div>
                    </li>
                </ol>
            </aside>
        </section>

        <section class="mt-14 border-t border-line pt-10 lg:mt-20" aria-labelledby="modules-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="font-mono text-sm font-semibold text-accent">02 / Foundation modules</p>
                    <h2 id="modules-heading" class="mt-2 text-2xl font-semibold text-ink sm:text-3xl">A teachable starting point</h2>
                </div>
                <p class="max-w-md text-sm leading-6 text-ink-muted">Each module uses standard Laravel boundaries suitable for gradual development.</p>
            </div>

            <div class="mt-8 grid border-y border-line md:grid-cols-3">
                <article class="border-b border-line py-7 md:border-r md:border-b-0 md:pr-7">
                    <p class="font-mono text-sm font-semibold text-primary-text">01</p>
                    <h3 class="mt-3 text-lg font-semibold text-ink">Routing and Blade</h3>
                    <p class="mt-2 leading-7 text-ink-muted">A public route returns a focused view through a small controller.</p>
                </article>
                <article class="border-b border-line py-7 md:border-r md:border-b-0 md:px-7">
                    <p class="font-mono text-sm font-semibold text-primary-text">02</p>
                    <h3 class="mt-3 text-lg font-semibold text-ink">Tailwind CSS</h3>
                    <p class="mt-2 leading-7 text-ink-muted">Approved color, spacing, type, and responsive rules compile into local assets.</p>
                </article>
                <article class="py-7 md:pl-7">
                    <p class="font-mono text-sm font-semibold text-primary-text">03</p>
                    <h3 class="mt-3 text-lg font-semibold text-ink">MySQL 8</h3>
                    <p class="mt-2 leading-7 text-ink-muted">The local and test databases use separate dedicated credentials.</p>
                </article>
            </div>
        </section>

        <section class="mt-10 grid gap-6 rounded-lg border border-line bg-surface-muted p-6 sm:p-8 md:grid-cols-[1fr_auto] md:items-center" aria-labelledby="not-ready-heading">
            <div>
                <h2 id="not-ready-heading" class="text-lg font-semibold text-ink">Not available yet</h2>
                <p class="mt-2 max-w-2xl leading-7 text-ink-muted">
                    Role management, courses, enrollment, quizzes, certificates, uploads, and PayMongo remain outside the current authentication slice.
                </p>
            </div>
            <p class="inline-flex w-fit items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-muted">
                <span class="size-2 rounded-full border border-current" aria-hidden="true"></span>
                Awaiting later approval
            </p>
        </section>
    </div>
@endsection
