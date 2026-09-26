@extends('layouts.app')

@section('title', 'Student home')

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <p class="font-mono text-sm font-semibold text-primary-text">Student workspace</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Welcome, {{ $user->name }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Your account is ready. Browse published courses and enroll in free courses to build your learning record.</p>

        @if ($continueLesson)
            <section class="mt-8 border-t-4 border-accent bg-surface p-6 shadow-sm" aria-labelledby="continue-learning-heading">
                <p class="font-mono text-sm font-semibold text-primary-text">Continue learning</p>
                <h2 id="continue-learning-heading" class="mt-2 text-lg font-semibold text-ink">{{ $continueLesson->title }}</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ $continueLesson->module->course->title }} · {{ $continueLesson->module->title }}</p>
                @if ($continueProgress?->last_viewed_at)
                    <p class="mt-1 text-sm text-ink-muted">Last opened {{ $continueProgress->last_viewed_at->diffForHumans() }}.</p>
                @endif
                <a href="{{ route('student.lessons.show', [$continueLesson->module->course, $continueLesson]) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Resume {{ $continueLesson->title }}</a>
            </section>
        @else
            <section class="mt-8 border-t-4 border-line bg-surface-muted p-6" aria-labelledby="continue-empty-heading">
                <h2 id="continue-empty-heading" class="text-lg font-semibold text-ink">No lessons opened yet</h2>
                <p class="mt-2 text-sm leading-6 text-ink-muted">Open a lesson in one of your courses and it appears here so you can pick up where you stopped.</p>
                <a href="{{ route('student.courses.index') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open My courses</a>
            </section>
        @endif

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <section class="border-t-4 border-primary bg-surface p-6 shadow-sm" aria-labelledby="account-summary-heading">
                <h2 id="account-summary-heading" class="text-lg font-semibold text-ink">Account summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-ink-muted">Role</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Status</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->account_status->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Email</dt><dd class="break-words font-medium text-ink">{{ $user->email }}</dd></div>
                </dl>
            </section>
            <section class="border-t-4 border-accent bg-surface-muted p-6" aria-labelledby="student-next-heading">
                <h2 id="student-next-heading" class="text-lg font-semibold text-ink">Available now</h2>
                <p class="mt-3 leading-7 text-ink-muted">Your profile, the published course catalog, free course enrollment, lesson reading, and lesson progress are available.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('student.courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">My courses</a>
                    <a href="{{ route('courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Browse catalog</a>
                </div>
            </section>
        </div>
    </div>
@endsection
