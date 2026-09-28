@extends('layouts.app-shell')

@section('title', 'Instructor dashboard')
@section('workspace-context', 'Teaching workspace')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Instructor workspace"
            :title="'Welcome back, '.$user->name"
            description="Build and publish your courses, then follow how students move through them."
        >
            <x-slot:actions>
                <x-btn :href="route('instructor.courses.index')" variant="secondary" size="md">
                    <x-icon name="book-open" size="sm" />
                    My courses
                </x-btn>
                <x-btn :href="route('instructor.courses.create')" variant="primary" size="md">
                    <x-icon name="plus" size="sm" />
                    Create course
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        {{-- The teaching summary. Every count is scoped to this Instructor, so
             another Instructor's work is never visible here. --}}
        <section class="mt-8" aria-labelledby="instructor-summary-heading">
            <h2 id="instructor-summary-heading" class="sr-only">Your teaching totals</h2>

            {{-- Five tiles, because an Instructor has one more question than a
                 Student does: how many of my courses are still drafts. The
                 completion rate is the figure that says whether the teaching is
                 landing, so it takes the place of the raw quiz count, which is
                 only interesting while authoring. --}}
            <dl role="list" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                <x-stat label="Courses" :value="$stats['courses']" tone="primary" hint="That you own" />
                <x-stat label="Published" :value="$stats['published_courses']" tone="primary" hint="Of your courses" />
                <x-stat label="Drafts" :value="$stats['draft_courses']" tone="primary" hint="Not published yet" />
                <x-stat label="Active students" :value="$stats['active_students']" tone="accent" hint="Studying right now" />
                <x-stat label="Completion rate" :value="$stats['completion_rate'].'%'" tone="accent"
                        hint="Finished out of started" />
            </dl>
        </section>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-3">
            {{-- The one chart here. It answers "where are my students", which a
                 list of course titles cannot, and it is built from the enrollment
                 counts already loaded for the course list, so it adds no query. --}}
            <x-bar-chart
                class="lg:col-span-2"
                heading="Students by course"
                description="Enrollments across your published and draft courses."
                :rows="$enrollmentRows"
                empty="Publish a course and your enrollments will appear here."
            />

            <x-activity-agenda
                heading="Recent activity"
                description="What has happened on your courses."
                :entries="$agenda"
                empty="Nothing has happened on your courses yet."
            />
        </div>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-3">
            {{-- Your courses. The primary working list for this role. --}}
            <section class="card lg:col-span-2" aria-labelledby="instructor-courses-heading">
                <div class="card-header">
                    <div>
                        <h2 id="instructor-courses-heading" class="text-base font-semibold text-ink">Your courses</h2>
                        <p class="mt-1 text-sm text-ink-muted">Only the courses you own are listed.</p>
                    </div>
                    <x-btn :href="route('instructor.courses.index')" variant="quiet" size="sm">
                        See all
                        <x-icon name="chevron-right" size="sm" />
                    </x-btn>
                </div>

                @if ($courses->isEmpty())
                    <x-empty-state
                        compact
                        icon="book-open"
                        title="No courses yet"
                        description="Create your first course to start building an outline. A course is private until you publish it."
                    >
                        <x-btn :href="route('instructor.courses.create')" variant="primary" size="md">
                            <x-icon name="plus" size="sm" />
                            Create a course
                        </x-btn>
                    </x-empty-state>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($courses as $course)
                            <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink">
                                        <a
                                            href="{{ route('instructor.courses.show', $course) }}"
                                            class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                        >{{ $course->title }}</a>
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
                                        <x-status :value="$course->status->value" />
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $course->active_enrollments }} enrolled</span>
                                        <span aria-hidden="true">·</span>
                                        <x-amount :minor="$course->price_minor" :type="$course->course_type" :currency="$course->currency" />
                                    </p>
                                </div>
                                <x-btn
                                    :href="route('instructor.courses.show', $course)"
                                    variant="secondary"
                                    size="sm"
                                    class="shrink-0 self-start sm:self-auto"
                                >Open outline</x-btn>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Student progress that needs attention. --}}
            <section class="card" aria-labelledby="instructor-attention-heading">
                <div class="card-header">
                    <div>
                        <h2 id="instructor-attention-heading" class="text-base font-semibold text-ink">
                            Student progress needing attention
                        </h2>
                        <p class="mt-1 text-sm text-ink-muted">Required lessons still open.</p>
                    </div>
                </div>

                @if ($needsAttention->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">
                        No student is behind on required lessons right now.
                    </p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($needsAttention as $enrollment)
                            <li class="px-5 py-4">
                                <p class="text-sm font-semibold text-ink">{{ $enrollment->student?->name ?? 'Student' }}</p>
                                <p class="mt-1 text-sm text-ink-muted">{{ $enrollment->course?->title ?? 'Course' }}</p>
                                <p class="mt-2 text-sm text-ink-muted">
                                    {{ $lessonsRemaining[$enrollment->id] ?? 0 }}
                                    {{ Str::plural('required lesson', $lessonsRemaining[$enrollment->id] ?? 0) }}
                                    still open.
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-2">
            {{-- Recent assessment results. --}}
            <section class="card" aria-labelledby="instructor-results-heading">
                <div class="card-header">
                    <div>
                        <h2 id="instructor-results-heading" class="text-base font-semibold text-ink">
                            Recent assessment results
                        </h2>
                        <p class="mt-1 text-sm text-ink-muted">Submitted attempts on your quizzes.</p>
                    </div>
                </div>

                @if ($recentResults->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">
                        No submitted attempt yet. A result appears here once a student submits a quiz.
                    </p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($recentResults as $attempt)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $attempt->student?->name ?? 'Student' }}</p>
                                    <p class="mt-0.5 truncate text-sm text-ink-muted">
                                        {{ $attempt->quiz?->title ?? 'Quiz' }}
                                        <span aria-hidden="true">·</span>
                                        Attempt {{ $attempt->attempt_number }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-sm font-semibold text-ink tabular-nums">{{ (int) $attempt->score_percent }}%</span>
                                    <x-status :value="$attempt->status->value" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Account facts. --}}
            <section class="card" aria-labelledby="instructor-account-heading">
                <div class="card-header">
                    <h2 id="instructor-account-heading" class="text-base font-semibold text-ink">Your account</h2>
                </div>

                <dl class="card-body space-y-4">
                    <div>
                        <dt class="meta-label">Name</dt>
                        <dd class="meta-value">{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt class="meta-label">Email</dt>
                        <dd class="meta-value">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="meta-label">Role</dt>
                        <dd class="mt-1">
                            <x-status
                                :value="$user->profile->role->value"
                                :label="\App\Support\StatusLabel::words($user->profile->role->value)"
                                tone="primary"
                            />
                        </dd>
                    </div>
                    <div>
                        <dt class="meta-label">Account status</dt>
                        <dd class="mt-1">
                            <x-status :value="$user->profile->account_status->value" />
                        </dd>
                    </div>
                </dl>

                <div class="border-t border-line p-4">
                    <x-btn :href="route('account.profile')" variant="secondary" size="md" block>
                        <x-icon name="user" size="sm" />
                        Edit your profile
                    </x-btn>
                </div>
            </section>
        </div>
    </div>
@endsection
