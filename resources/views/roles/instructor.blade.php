@extends('layouts.app')

@section('title', 'Instructor home')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <p class="font-mono text-sm font-semibold text-primary-text">Instructor workspace</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Welcome, {{ $user->name }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Build and publish your courses, then follow how students move through them.</p>

        <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4" role="list">
            <div class="border-t-4 border-primary bg-surface p-5">
                <dt class="text-sm text-ink-muted">Courses</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['courses'] }}</dd>
            </div>
            <div class="border-t-4 border-primary bg-surface p-5">
                <dt class="text-sm text-ink-muted">Published</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['published_courses'] }}</dd>
            </div>
            <div class="border-t-4 border-accent bg-surface p-5">
                <dt class="text-sm text-ink-muted">Students enrolled</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['students_enrolled'] }}</dd>
            </div>
            <div class="border-t-4 border-accent bg-surface p-5">
                <dt class="text-sm text-ink-muted">Quizzes</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['quizzes'] }}</dd>
            </div>
        </dl>

        <section class="mt-8 border border-line bg-surface p-6" aria-labelledby="instructor-courses-heading">
            <h2 id="instructor-courses-heading" class="text-lg font-semibold text-ink">Your courses</h2>

            @if ($courses->isEmpty())
                <p class="mt-3 text-sm leading-6 text-ink-muted">No courses yet. Create your first course to start building an outline.</p>
                <a href="{{ route('instructor.courses.create') }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Create a course</a>
            @else
                <ul class="mt-4 divide-y divide-line border-y border-line" role="list">
                    @foreach ($courses as $course)
                        <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-ink">
                                    <a href="{{ route('instructor.courses.show', $course) }}" class="rounded-md hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">{{ $course->title }}</a>
                                </p>
                                <p class="mt-1 text-sm text-ink-muted">{{ ucfirst($course->status->value) }} · {{ $course->active_enrollments }} enrolled</p>
                            </div>
                            <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text underline underline-offset-4 hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open outline</a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('instructor.courses.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-primary-text underline underline-offset-4 hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">See all courses</a>
            @endif
        </section>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <section class="border border-line bg-surface p-6" aria-labelledby="instructor-summary-heading">
                <h2 id="instructor-summary-heading" class="text-lg font-semibold text-ink">Account summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-ink-muted">Role</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Status</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->account_status->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Email</dt><dd class="break-words font-medium text-ink">{{ $user->email }}</dd></div>
                </dl>
            </section>
            <section class="border border-line bg-surface-muted p-6" aria-labelledby="instructor-next-heading">
                <h2 id="instructor-next-heading" class="text-lg font-semibold text-ink">Available now</h2>
                <p class="mt-3 leading-7 text-ink-muted">Course authoring, publishing, reordering, archiving, quizzes, and private materials are available.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('instructor.courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open course workspace</a>
                    <a href="{{ route('account.profile') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open profile</a>
                </div>
            </section>
        </div>
    </div>
@endsection
