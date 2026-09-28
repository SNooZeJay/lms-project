@extends('layouts.app-shell')

@section('title', $course->title)
@section('workspace-context', 'My courses')

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <header class="border-b border-line pb-8">
            <div class="flex flex-wrap items-center gap-2">
                <p class="eyebrow">Enrolled course</p>
                <x-status :value="$enrollment->status->value" />
            </div>

            <h1 class="mt-3 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                {{ $course->title }}
            </h1>

            @if ($course->description)
                <p class="prose-measure mt-4 text-lg leading-8 text-ink-muted">{{ $course->description }}</p>
            @endif

            <dl class="mt-6 grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="meta-label">Instructor</dt>
                    <dd class="meta-value">{{ $course->instructor?->name ?? 'IT Learning Hub' }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Level</dt>
                    <dd class="meta-value">{{ \App\Support\StatusLabel::words($course->level->value) }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Modules</dt>
                    <dd class="meta-value tabular-nums">{{ $course->modules->count() }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Lessons</dt>
                    <dd class="meta-value tabular-nums">{{ $course->modules->sum(fn ($module) => $module->lessons->count()) }}</dd>
                </div>
            </dl>

                {{--
                    Talking to the person teaching this course.

                    Opening the thread has been possible since direct messaging was
                    approved, and no page posted to it. A Student could open a support
                    request and could reply inside a thread, and could never once
                    start one with the instructor of the course they are taking, which
                    is the conversation the plan lists first.

                    One of two things, never both: the button when there is no thread,
                    and a link to the thread when there is. Offering the button while
                    a thread is open would ask somebody to start a conversation they
                    are already in.

                    The gate is the policy's own answer, so the rule about who may
                    open this thread stays in one place.
                --}}
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @if ($courseThread)
                        <x-btn :href="route('conversations.show', $courseThread)" variant="secondary" size="md">
                            <x-icon name="message-square" size="sm" />
                            Open your conversation with the instructor
                        </x-btn>
                        <p class="text-sm text-ink-muted">
                            You already have a thread about this course.
                        </p>
                    @else
                        <form method="POST" action="{{ route('conversations.course.store', $course) }}" data-pending>
                            @csrf
                            <x-btn type="submit" variant="secondary" size="md" data-pending-button>
                                <x-icon name="message-square" size="sm" />
                                <span data-pending-text>Ask the instructor a question</span>
                            </x-btn>
                        </form>
                        <p class="text-sm text-ink-muted">
                            Opens one conversation with {{ $course->instructor?->name ?? 'the instructor' }} about this course.
                        </p>
                    @endif
                </div>

            @if ($course->status !== \App\Enums\CourseStatus::Published)
                <x-note tone="warning" class="mt-6">
                    This course is not published right now. Your enrollment and your completed lessons are kept,
                    and you keep your access to the lessons that are still published.
                </x-note>
            @endif
        </header>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            {{-- Progress. One real percentage, calculated on the server. --}}
            <section class="card p-5 lg:col-span-2" aria-labelledby="course-progress-heading">
                <h2 id="course-progress-heading" class="text-base font-semibold text-ink">Course progress</h2>

                <div class="mt-4">
                    @if ($showProgress)
                        <x-progress
                            :percentage="$progress['percentage']"
                            label="Lesson progress"
                            :detail="$progress['completed'].' of '.$progress['total'].' required published lessons completed. Optional lessons are not counted.'"
                        />
                    @else
                        <x-note tone="neutral">
                            <span class="font-semibold">Progress is hidden.</span>
                            This course is not published, so the percentage is hidden. Your completed lessons are
                            kept and reappear when the course is published again.
                        </x-note>
                    @endif
                </div>
            </section>

            {{-- The certificate panel. Either the certificate, a claim button,
                 or the exact list of what is still missing. --}}
            <section class="card p-5" aria-labelledby="course-certificate-heading">
                <h2 id="course-certificate-heading" class="text-base font-semibold text-ink">Certificate</h2>

                <div class="mt-4">
                    @if ($certificate)
                        <p class="text-sm leading-6 text-ink-muted">
                            You completed this course on
                            {{ $certificate->completion_date->format('M j, Y') }}.
                        </p>
                        <x-btn
                            :href="route('student.certificates.show', $certificate)"
                            variant="primary"
                            size="md"
                            class="mt-4"
                        >
                            <x-icon name="award" size="sm" />
                            View certificate
                        </x-btn>
                    @elseif ($completion['eligible'])
                        <p class="text-sm leading-6 text-ink-muted">You meet every requirement for this course.</p>
                        <form method="POST" action="{{ route('student.courses.complete', $course) }}" class="mt-4" data-pending>
                            @csrf
                            <x-btn type="submit" variant="primary" size="md" block data-pending-button>
                                <x-icon name="award" size="sm" />
                                <span data-pending-text>Claim certificate</span>
                            </x-btn>
                        </form>
                    @else
                        <p class="text-sm leading-6 text-ink-muted">Still to do before you can claim a certificate:</p>
                        <ul role="list" class="mt-2 space-y-1.5">
                            @foreach ($completion['reasons'] as $reason)
                                <li class="flex gap-2 text-sm leading-6 text-ink-muted">
                                    <x-icon name="close" size="sm" class="mt-1 shrink-0 text-ink-subtle" />
                                    <span>{{ $reason }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <section class="mt-10" aria-labelledby="student-outline-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="student-outline-heading" class="text-xl font-semibold text-ink">Your lessons</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-muted">
                        Open any lesson to read its content and materials, then mark it complete.
                    </p>
                </div>
            </div>

            @forelse ($course->modules as $module)
                <section class="card mt-4 overflow-hidden" aria-labelledby="student-module-{{ $module->id }}">
                    <div class="border-b border-line bg-surface-muted px-5 py-4">
                        <p class="eyebrow">Module {{ $module->position }}</p>
                        <h3 id="student-module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">
                            {{ $module->title }}
                        </h3>
                    </div>

                    @if ($module->lessons->isEmpty())
                        <p class="px-5 py-5 text-sm text-ink-muted">No published lessons in this module yet.</p>
                    @else
                        <ol role="list" class="divide-y divide-line">
                            @foreach ($module->lessons as $lesson)
                                @php $isComplete = $completedLessonIds->has($lesson->id); @endphp
                                <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                            Lesson {{ $lesson->position }}
                                        </p>
                                        <p class="mt-1 font-semibold text-ink">
                                            <a
                                                href="{{ route('student.lessons.show', [$course, $lesson]) }}"
                                                class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                            >{{ $lesson->title }}</a>
                                        </p>
                                        @if ($lesson->summary)
                                            <p class="mt-1 max-w-2xl text-sm leading-6 text-ink-muted">{{ $lesson->summary }}</p>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        @if ($isComplete)
                                            <x-badge tone="success">
                                                <x-icon name="check" size="xs" />
                                                Completed
                                            </x-badge>
                                        @endif
                                        <x-badge tone="neutral">{{ $lesson->is_required ? 'Required' : 'Optional' }}</x-badge>
                                        @if ($lesson->estimated_minutes)
                                            <x-badge tone="neutral">{{ $lesson->estimated_minutes }} min</x-badge>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @empty
                <div class="card mt-4">
                    <x-empty-state
                        compact
                        icon="book-open"
                        title="This course has no published lessons yet"
                        description="Your enrollment is kept. Lessons appear here as soon as the Instructor publishes them."
                    />
                </div>
            @endforelse
        </section>

        @if ($quizzes->isNotEmpty())
            <section class="mt-12" aria-labelledby="course-quizzes-heading">
                <div>
                    <h2 id="course-quizzes-heading" class="text-xl font-semibold text-ink">Quizzes</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-muted">
                        Answer every question, then submit once. Three attempts are allowed, and the answer key is
                        never shown before you submit.
                    </p>
                </div>

                <ul role="list" class="mt-5 space-y-3">
                    @foreach ($quizzes as $quiz)
                        <li class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">
                                    <a
                                        href="{{ route('student.quizzes.show', [$course, $quiz]) }}"
                                        class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                    >{{ $quiz->title }}</a>
                                </p>
                                @if ($quiz->description)
                                    <p class="mt-1 max-w-2xl text-sm leading-6 text-ink-muted">{{ $quiz->description }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                @if ($quiz->is_required)
                                    <x-badge tone="primary">Required</x-badge>
                                @endif
                                <x-status
                                    :value="$quizState[$quiz->id] ?? 'not_attempted'"
                                />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
