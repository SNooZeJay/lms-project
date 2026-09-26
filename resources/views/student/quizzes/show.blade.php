@extends('layouts.app')

@section('title', $quiz->title)

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.courses.show', $course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to {{ $course->title }}</a>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        <header class="mt-6">
            <p class="font-mono text-sm font-semibold text-primary-text">Quiz</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $quiz->title }}</h1>
            @if ($quiz->description)
                <p class="mt-3 max-w-2xl leading-7 text-ink-muted">{{ $quiz->description }}</p>
            @endif

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-ink-muted">Questions</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $questions->count() }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Passing score</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ rtrim(rtrim((string) $quiz->passing_score_percent, '0'), '.') }}%</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Attempts</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $attemptsUsed }} of {{ $maxAttempts }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">State</dt>
                    <dd class="mt-1 font-semibold text-ink">
                        @if ($hasPassed)
                            Passed
                        @elseif ($attemptsUsed === 0)
                            Not attempted
                        @else
                            Not passed
                        @endif
                    </dd>
                </div>
            </dl>
        </header>

        @if ($quiz->instructions)
            <section class="mt-8 border-l-4 border-line bg-surface-muted px-4 py-3" aria-labelledby="quiz-instructions-heading">
                <h2 id="quiz-instructions-heading" class="text-sm font-semibold text-ink">Instructions</h2>
                <p class="mt-1 text-sm leading-6 text-ink-muted">{{ $quiz->instructions }}</p>
            </section>
        @endif

        <section class="mt-8" aria-labelledby="quiz-questions-heading">
            <h2 id="quiz-questions-heading" class="text-xl font-semibold text-ink">Questions</h2>
            <p class="mt-1 text-sm text-ink-muted">Read every question before you start. Answers cannot be changed after submitting.</p>

            <ol class="mt-5 space-y-5" role="list">
                @foreach ($questions as $index => $question)
                    <li class="border border-line bg-surface p-5">
                        <h3 class="font-semibold text-ink">
                            <span class="font-mono text-xs text-ink-muted">Question {{ $index + 1 }}</span>
                            <span class="mt-1 block">{{ $question->prompt }}</span>
                        </h3>
                        <ul class="mt-3 space-y-2" role="list">
                            @foreach ($question->options as $option)
                                <li class="text-sm text-ink-muted">{{ $option->option_text }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="mt-8 border-t border-line pt-6" aria-labelledby="quiz-start-heading">
            <h2 id="quiz-start-heading" class="text-lg font-semibold text-ink">Take this quiz</h2>

            @if ($hasPassed)
                <p class="mt-2 text-sm leading-6 text-ink-muted">You already passed this quiz. No further attempts are needed.</p>
            @elseif ($openAttempt)
                <p class="mt-2 text-sm leading-6 text-ink-muted">You have an attempt in progress.</p>
                <a href="{{ route('student.quizzes.attempts.show', [$course, $quiz, $openAttempt]) }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Resume attempt {{ $openAttempt->attempt_number }}</a>
            @elseif ($attemptsUsed >= $maxAttempts)
                <p class="mt-2 text-sm leading-6 text-ink-muted">You have used all {{ $maxAttempts }} attempts for this quiz.</p>
            @else
                <p class="mt-2 text-sm leading-6 text-ink-muted">Starting uses one of your {{ $maxAttempts }} attempts.</p>
                <form method="POST" action="{{ route('student.quizzes.start', [$course, $quiz]) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Start attempt {{ $attemptsUsed + 1 }}</button>
                </form>
            @endif
        </section>

        @if ($attempts->isNotEmpty())
            <section class="mt-8" aria-labelledby="quiz-history-heading">
                <h2 id="quiz-history-heading" class="text-lg font-semibold text-ink">Your attempts</h2>
                <ul class="mt-3 divide-y divide-line border-y border-line" role="list">
                    @foreach ($attempts as $attempt)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span class="text-sm text-ink">Attempt {{ $attempt->attempt_number }}</span>
                            @if ($attempt->status === \App\Enums\QuizAttemptStatus::InProgress)
                                <a href="{{ route('student.quizzes.attempts.show', [$course, $quiz, $attempt]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text underline underline-offset-4 hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">In progress</a>
                            @else
                                <span class="text-sm font-semibold {{ $attempt->passed ? 'text-success-text' : 'text-ink-muted' }}">
                                    {{ $attempt->passed ? 'Passed' : 'Failed' }} at {{ rtrim(rtrim((string) $attempt->score_percent, '0'), '.') }}%
                                </span>
                                <a href="{{ route('student.quizzes.attempts.result', [$course, $quiz, $attempt]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text underline underline-offset-4 hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Review</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
