@extends('layouts.app-shell')

@section('title', 'My courses')
@section('workspace-context', 'Learning workspace')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Student workspace"
            title="My courses"
            description="These are your own enrollments. No one else can see this list."
        >
            <x-slot:actions>
                <x-btn :href="route('courses.index')" variant="secondary" size="md">
                    <x-icon name="search" size="sm" />
                    Browse catalog
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mt-6" />

        @if ($enrollments->isEmpty())
            <div class="card mt-8">
                <x-empty-state
                    icon="book-open"
                    title="No enrollments yet"
                    description="Enroll in a published free course to start your learning record. A paid course asks for payment before it opens."
                >
                    <x-btn :href="route('courses.index')" variant="primary" size="md">
                        Browse published courses
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            <ul role="list" class="mt-8 grid gap-5 sm:grid-cols-2">
                @foreach ($enrollments as $enrollment)
                    @php
                        $course = $enrollment->course;
                        $paymentState = $paymentStates[$enrollment->id] ?? null;
                        $progress = $progressByEnrollment[$enrollment->id];
                    @endphp
                    <li class="card flex flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="text-lg font-semibold text-ink">
                                {{ $course?->title ?? 'Removed course' }}
                            </h2>
                            @if ($paymentState)
                                <x-badge :tone="$paymentState->tone" class="shrink-0">{{ $paymentState->label }}</x-badge>
                            @endif
                        </div>

                        <p class="mt-2 text-sm text-ink-muted">{{ $course?->category ?: 'Uncategorized' }}</p>

                        <div class="mt-4">
                            @if ($progress['visible'])
                                <x-progress
                                    :percentage="$progress['percentage']"
                                    label="Lesson progress"
                                    :detail="$progress['completed'].' of '.$progress['total'].' required published lessons completed.'"
                                    size="sm"
                                />
                            @else
                                <x-note tone="neutral">
                                    This course is no longer published. Your enrollment is kept.
                                    Every lesson you already completed is kept too.
                                </x-note>
                            @endif
                        </div>

                        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm min-[360px]:grid-cols-2">
                            <div>
                                <dt class="meta-label">Type</dt>
                                <dd class="mt-1 font-medium text-ink">
                                    {{ $course ? \App\Support\StatusLabel::words($course->course_type->value) : 'Unknown' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="meta-label">Enrolled</dt>
                                <dd class="mt-1 font-medium text-ink">
                                    {{ $enrollment->activated_at?->format('M j, Y') ?? 'Waiting' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="meta-label">Instructor</dt>
                                <dd class="mt-1 font-medium text-ink">{{ $course?->instructor?->name ?? 'IT Learning Hub' }}</dd>
                            </div>
                            <div>
                                <dt class="meta-label">Price</dt>
                                <dd class="mt-1 font-medium text-ink">
                                    @if ($course)
                                        <x-amount :minor="$course->price_minor" :type="$course->course_type" :currency="$course->currency" />
                                    @else
                                        Unknown
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if ($paymentState)
                            <x-note :tone="$paymentState->tone" class="mt-4">{{ $paymentState->message }}</x-note>
                        @endif

                        <div class="mt-5 flex flex-col gap-3">
                            @if ($enrollment->grantsAccess() && $course)
                                <x-btn :href="route('student.courses.show', $course)" variant="primary" size="md" block>
                                    Open course
                                </x-btn>
                            @elseif ($paymentState?->offersPayment && $course)
                                <form method="POST" action="{{ route('student.payments.checkout', $course) }}" data-pending>
                                    @csrf
                                    <x-btn type="submit" variant="primary" size="md" block data-pending-button>
                                        <span data-pending-text>{{ $paymentState->actionLabel }}</span>
                                    </x-btn>
                                </form>
                            @endif

                            @if ($course?->status === \App\Enums\CourseStatus::Published)
                                <x-btn :href="route('courses.show', $course)" variant="secondary" size="md" block>
                                    View the public course page
                                </x-btn>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $enrollments->links() }}</div>
        @endif
    </div>
@endsection
