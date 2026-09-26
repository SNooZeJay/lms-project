@extends('layouts.app')

@section('title', 'Instructor home')

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <p class="font-mono text-sm font-semibold text-primary-text">Instructor workspace</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Welcome, {{ $user->name }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Your Instructor access is active. Course authoring will be added after the authorization phase.</p>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <section class="border-t-4 border-primary bg-surface p-6 shadow-sm" aria-labelledby="instructor-summary-heading">
                <h2 id="instructor-summary-heading" class="text-lg font-semibold text-ink">Account summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-ink-muted">Role</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Status</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->account_status->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Email</dt><dd class="break-words font-medium text-ink">{{ $user->email }}</dd></div>
                </dl>
            </section>
            <section class="border-t-4 border-accent bg-surface-muted p-6" aria-labelledby="instructor-next-heading">
                <h2 id="instructor-next-heading" class="text-lg font-semibold text-ink">Available now</h2>
                <p class="mt-3 leading-7 text-ink-muted">Your profile and Instructor course workspace are available.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('instructor.courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open course workspace</a>
                    <a href="{{ route('account.profile') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open profile</a>
                </div>
            </section>
        </div>
    </div>
@endsection
