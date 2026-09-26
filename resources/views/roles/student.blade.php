@extends('layouts.app-shell')

@section('title', 'Student dashboard')
@section('workspace-context', 'Learning workspace')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Student workspace"
            :title="'Welcome back, '.$user->name"
            description="Pick up where you stopped, check your course progress, and see what is waiting for you."
        >
            <x-slot:actions>
                <x-btn :href="route('student.courses.index')" variant="secondary" size="md">
                    <x-icon name="book-open" size="sm" />
                    My courses
                </x-btn>
                <x-btn :href="route('courses.index')" variant="primary" size="md">
                    <x-icon name="search" size="sm" />
                    Browse catalog
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        {{-- The counted numbers. Every label says exactly what it counts, and
             every number comes from the one report service, so a figure can
             never disagree with itself across pages. --}}
        <section class="mt-8" aria-labelledby="student-summary-heading">
            <h2 id="student-summary-heading" class="sr-only">Your totals</h2>

            {{-- The label on every tile names exactly what is counted, in plain
                 words. "Courses enrolled" would need a second reading to know
                 whose courses and which state, so each label says so. --}}
            <dl role="list" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat label="Courses enrolled" :value="$stats['courses_enrolled']" tone="primary"
                        hint="Active or completed" />
                <x-stat label="Lessons completed" :value="$stats['lessons_completed']" tone="primary" />
                <x-stat label="Quizzes passed" :value="$stats['quizzes_passed']" tone="accent" />
                <x-stat label="Certificates earned" :value="$stats['certificates_earned']" tone="accent" />
            </dl>
        </section>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            {{--
                Continue learning is the primary focus of this page, so it takes
                the wider column.

                The panel only exists when there is a real Lesson to return to. An
                empty panel is not drawn with a disabled button, because that
                would promise a lesson that does not exist; a separate empty state
                below explains the next action instead.
            --}}
            @if ($continueLesson)
                <section class="card-accent-edge lg:col-span-2" aria-labelledby="continue-learning-heading">
                    <div class="card-header">
                        <div>
                            <p class="eyebrow">Continue learning</p>
                            <h2 id="continue-learning-heading" class="mt-1 text-lg font-semibold text-ink">
                                {{ $continueLesson->title }}
                            </h2>
                        </div>
                        <x-badge tone="info">
                            {{ $continueProgress->status->value === 'completed' ? 'Completed' : 'In progress' }}
                        </x-badge>
                    </div>

                    <div class="card-body">
                        <p class="text-sm text-ink-muted">
                            {{ $continueLesson->module->course->title }}
                            <span aria-hidden="true">·</span>
                            {{ $continueLesson->module->title }}
                        </p>

                        @if ($continueProgress?->last_viewed_at)
                            <p class="mt-2 text-sm text-ink-muted">
                                Last opened {{ $continueProgress->last_viewed_at->diffForHumans() }}.
                            </p>
                        @endif

                        <x-btn
                            :href="route('student.lessons.show', [$continueLesson->module->course, $continueLesson])"
                            variant="primary"
                            size="lg"
                            class="mt-5"
                        >
                            Resume this lesson
                            <x-icon name="arrow-right" size="sm" />
                        </x-btn>
                    </div>
                </section>
            @else
                <section class="card lg:col-span-2" aria-labelledby="nothing-to-resume-heading">
                    <div class="card-header">
                        <h2 id="nothing-to-resume-heading" class="text-base font-semibold text-ink">
                            No lessons opened yet
                        </h2>
                    </div>

                    <div class="card-body">
                        <x-empty-state
                            compact
                            icon="book-open"
                            title="Nothing to pick up yet"
                            description="Open any lesson in one of your courses and it appears here, so you can return to it without hunting for it again."
                        >
                            <x-btn :href="route('student.courses.index')" variant="primary" size="md">
                                Open my courses
                            </x-btn>
                            <x-btn :href="route('courses.index')" variant="secondary" size="md">
                                Browse the catalog
                            </x-btn>
                        </x-empty-state>
                    </div>
                </section>
            @endif

            {{-- Account facts. A role, a status, and a verified email, in words. --}}
            <section class="card" aria-labelledby="student-account-heading">
                <div class="card-header">
                    <h2 id="student-account-heading" class="text-base font-semibold text-ink">Your account</h2>
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
                    <div>
                        <dt class="meta-label">Email confirmation</dt>
                        <dd class="mt-1">
                            <x-status :value="$user->hasVerifiedEmail() ? 'verified' : 'unverified'" />
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

        {{-- Course progress, one real percentage per course. The value is
             calculated on the server from stored records. --}}
        <section class="mt-8" aria-labelledby="student-courses-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="student-courses-heading" class="text-xl font-semibold text-ink">My courses</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-muted">
                        Your progress comes from completed required lessons. Optional lessons never change it.
                    </p>
                </div>
                <x-btn :href="route('student.courses.index')" variant="quiet" size="md">
                    See all courses
                    <x-icon name="chevron-right" size="sm" />
                </x-btn>
            </div>

            @if ($courses->isEmpty())
                <div class="card mt-4">
                    <x-empty-state
                        compact
                        icon="book-open"
                        title="You are not enrolled in a course yet"
                        description="Browse the published catalog and enroll in a free course to start building your learning record."
                    >
                        <x-btn :href="route('courses.index')" variant="primary" size="md">
                            Browse the catalog
                        </x-btn>
                    </x-empty-state>
                </div>
            @else
                <ul role="list" class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ($courses as $enrollment)
                        @php $progress = $progressByCourse[$enrollment->id]; @endphp
                        <li class="card flex flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-base font-semibold text-ink">
                                    <a
                                        href="{{ route('student.courses.show', $enrollment->course) }}"
                                        class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                    >{{ $enrollment->course->title }}</a>
                                </h3>
                                <x-status :value="$enrollment->status->value" />
                            </div>

                            <p class="mt-1 text-sm text-ink-muted">
                                {{ $enrollment->course->instructor?->name ?? 'IT Learning Hub' }}
                            </p>

                            <div class="mt-4">
                                @if ($enrollment->course->status === \App\Enums\CourseStatus::Published)
                                    <x-progress
                                        :percentage="$progress['percentage']"
                                        label="Lesson progress"
                                        :detail="$progress['completed'].' of '.$progress['total'].' required published lessons completed.'"
                                        size="sm"
                                    />
                                @else
                                    <x-note tone="neutral">
                                        This course is not published, so the percentage is hidden. Your completed
                                        lessons are kept.
                                    </x-note>
                                @endif
                            </div>

                            <x-btn
                                :href="route('student.courses.show', $enrollment->course)"
                                variant="secondary"
                                size="sm"
                                class="mt-5 self-start"
                            >Open course</x-btn>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            {{-- Recent assessments. --}}
            <section class="card" aria-labelledby="student-attempts-heading">
                <div class="card-header">
                    <div>
                        <h2 id="student-attempts-heading" class="text-base font-semibold text-ink">Recent assessments</h2>
                        <p class="mt-1 text-sm text-ink-muted">Your own quiz attempts, newest first.</p>
                    </div>
                </div>

                @if ($recentAttempts->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">
                        No quiz attempt yet. Quizzes appear on a course page once you enroll.
                    </p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($recentAttempts as $attempt)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $attempt->quiz?->title ?? 'Quiz' }}</p>
                                    <p class="mt-0.5 truncate text-sm text-ink-muted">
                                        {{ $attempt->quiz?->course?->title }}
                                        <span aria-hidden="true">·</span>
                                        {{ $attempt->submitted_at?->format('M j, Y') }}
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

            {{-- Payment history. --}}
            <section class="card" aria-labelledby="student-payments-heading">
                <div class="card-header">
                    <div>
                        <h2 id="student-payments-heading" class="text-base font-semibold text-ink">Payment history</h2>
                        <p class="mt-1 text-sm text-ink-muted">Every amount comes from the stored payment record.</p>
                    </div>
                </div>

                @if ($recentPayments->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">
                        No payment yet. A free course needs no payment, and a paid course shows its exact amount
                        before you start checkout.
                    </p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($recentPayments as $payment)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $payment->course?->title ?? 'Course' }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-ink-muted">{{ $payment->reference ?? 'No reference yet' }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-amount :minor="$payment->amount_minor" :currency="$payment->currency" class="text-sm font-semibold" />
                                    <x-status :value="$payment->status->value" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="mt-8" aria-labelledby="student-certificates-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="student-certificates-heading" class="text-xl font-semibold text-ink">Certificates</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-muted">
                        A certificate appears after every course requirement is verified.
                    </p>
                </div>
                <x-btn :href="route('student.certificates.index')" variant="quiet" size="md">
                    Open certificates
                    <x-icon name="chevron-right" size="sm" />
                </x-btn>
            </div>

            @if ($stats['certificates_earned'] === 0)
                <div class="card mt-4">
                    <x-empty-state
                        compact
                        icon="award"
                        title="No certificate yet"
                        description="Complete the required lessons and pass the required quizzes in a course, then claim your certificate."
                    >
                        <x-btn :href="route('student.courses.index')" variant="secondary" size="md">
                            Go to my courses
                        </x-btn>
                    </x-empty-state>
                </div>
            @else
                <div class="card mt-4 p-5">
                    <p class="text-sm leading-6 text-ink-muted">
                        You have earned {{ $stats['certificates_earned'] }}
                        {{ Str::plural('certificate', $stats['certificates_earned']) }}.
                        Open the certificate page to view or print one.
                    </p>
                </div>
            @endif
        </section>
    </div>
@endsection
