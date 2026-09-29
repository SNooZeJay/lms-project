@extends('layouts.app-shell')

@section('title', $quiz->title)
@section('workspace-context', $course->title)

@section('content')
@section('measure', 'medium')
            @if (session('status'))
            <x-note tone="success" class="mb-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mb-6" />

        <header class="border-b border-line pb-8">
            <p class="eyebrow">Quiz</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                {{ $quiz->title }}
            </h1>

            @if ($quiz->description)
                <p class="prose-measure mt-3 leading-7 text-ink-muted">{{ $quiz->description }}</p>
            @endif

            {{-- Facts a Student needs before starting. Attempts used is always
                 shown, so a blocked Student knows exactly why. --}}
            <dl class="mt-6 grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="meta-label">Questions</dt>
                    <dd class="meta-value tabular-nums">{{ $questions->count() }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Passing score</dt>
                    <dd class="meta-value tabular-nums">{{ rtrim(rtrim((string) $quiz->passing_score_percent, '0'), '.') }}%</dd>
                </div>
                <div>
                    <dt class="meta-label">Attempts used</dt>
                    <dd class="meta-value tabular-nums">{{ $attemptsUsed }} of {{ $maxAttempts }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Result</dt>
                    <dd class="mt-1">
                        @if ($hasPassed)
                            <x-status value="passed" />
                        @elseif ($attemptsUsed === 0)
                            <x-status value="not_attempted" />
                        @else
                            <x-status value="not_passed" />
                        @endif
                    </dd>
                </div>
            </dl>
        </header>

        @if ($quiz->instructions)
            <section class="mt-8" aria-labelledby="quiz-instructions-heading">
                <h2 id="quiz-instructions-heading" class="text-sm font-semibold text-ink">Instructions</h2>
                <x-note tone="neutral" class="mt-2">{{ $quiz->instructions }}</x-note>
            </section>
        @endif

        {{-- Every question with its options. The correct answer is never marked
             here, and no explanation is shown before submitting. --}}
        <section class="mt-8" aria-labelledby="quiz-questions-heading">
            <h2 id="quiz-questions-heading" class="text-xl font-semibold text-ink">Questions</h2>
            <p class="mt-1 text-sm leading-6 text-ink-muted">
                Read every question before you start. Answers cannot be changed after submitting.
            </p>

            <ol role="list" class="mt-5 space-y-4">
                @foreach ($questions as $index => $question)
                    <li class="card p-5">
                        <h3 class="font-semibold text-ink">
                            <span class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                Question {{ $index + 1 }}
                            </span>
                            <span class="mt-1 block leading-7">{{ $question->prompt }}</span>
                        </h3>

                        <ul role="list" class="mt-3 space-y-1.5">
                            @foreach ($question->options as $option)
                                <li class="flex gap-3 text-sm leading-6 text-ink-muted">
                                    <span class="font-mono text-xs text-ink-subtle" aria-hidden="true">
                                        {{ chr(65 + $loop->index) }}.
                                    </span>
                                    <span>{{ $option->option_text }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="mt-8 card p-5" aria-labelledby="quiz-start-heading">
            <h2 id="quiz-start-heading" class="text-lg font-semibold text-ink">Take this quiz</h2>

            @if ($hasPassed)
                <p class="mt-2 text-sm leading-6 text-ink-muted">
                    You already passed this quiz. No further attempts are needed.
                </p>
            @elseif ($openAttempt)
                <p class="mt-2 text-sm leading-6 text-ink-muted">You have an attempt in progress.</p>
                <x-btn
                    :href="route('student.quizzes.attempts.show', [$course, $quiz, $openAttempt])"
                    variant="primary"
                    size="lg"
                    class="mt-4"
                >
                    <x-icon name="play" size="sm" />
                    Resume attempt {{ $openAttempt->attempt_number }}
                </x-btn>
            @elseif ($attemptsUsed >= $maxAttempts)
                <x-note tone="warning" class="mt-3">
                    You have used all {{ $maxAttempts }} attempts for this quiz, so no new attempt can be started.
                </x-note>
            @else
                <p class="mt-2 text-sm leading-6 text-ink-muted">
                    Starting uses one of your {{ $maxAttempts }} attempts. {{ $maxAttempts - $attemptsUsed }}
                    {{ Str::plural('attempt', $maxAttempts - $attemptsUsed) }} remain.
                </p>
                <form method="POST" action="{{ route('student.quizzes.start', [$course, $quiz]) }}" class="mt-4" data-pending>
                    @csrf
                    <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                        <x-icon name="play" size="sm" />
                        <span data-pending-text>Start attempt {{ $attemptsUsed + 1 }}</span>
                    </x-btn>
                </form>
            @endif
        </section>

        @if ($attempts->isNotEmpty())
            <section class="mt-8" aria-labelledby="quiz-history-heading">
                <h2 id="quiz-history-heading" class="text-lg font-semibold text-ink">Your attempts</h2>

                <ul role="list" class="card mt-3 divide-y divide-line">
                    @foreach ($attempts as $attempt)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <span class="text-sm font-medium text-ink">Attempt {{ $attempt->attempt_number }}</span>

                            @if ($attempt->status === \App\Enums\QuizAttemptStatus::InProgress)
                                <x-badge tone="info">In progress</x-badge>
                                <x-btn
                                    :href="route('student.quizzes.attempts.show', [$course, $quiz, $attempt])"
                                    variant="secondary"
                                    size="sm"
                                >Resume</x-btn>
                            @else
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-status :value="$attempt->status->value" />
                                    <span class="text-sm font-semibold text-ink tabular-nums">
                                        {{ rtrim(rtrim((string) $attempt->score_percent, '0'), '.') }}%
                                    </span>
                                    <x-btn
                                        :href="route('student.quizzes.attempts.result', [$course, $quiz, $attempt])"
                                        variant="secondary"
                                        size="sm"
                                    >Review</x-btn>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
@endsection
