@extends('layouts.app')

@section('title', 'Quiz result')

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.quizzes.show', [$course, $quiz]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to {{ $quiz->title }}</a>

        <header class="mt-6">
            <p class="font-mono text-sm font-semibold text-primary-text">Attempt {{ $attempt->attempt_number }} result</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $quiz->title }}</h1>
        </header>

        <section class="mt-6 border-l-4 {{ $attempt->passed ? 'border-accent bg-success-surface' : 'border-line bg-surface-muted' }} px-4 py-4" aria-labelledby="quiz-score-heading">
            <h2 id="quiz-score-heading" class="text-lg font-semibold text-ink">{{ $attempt->passed ? 'Passed' : 'Not passed' }}</h2>
            <p class="mt-1 text-sm text-ink-muted">
                {{ rtrim(rtrim((string) $attempt->score_percent, '0'), '.') }}% · {{ rtrim(rtrim((string) $attempt->score_points, '0'), '.') }} of {{ rtrim(rtrim((string) $attempt->total_points, '0'), '.') }} points.
                Passing needs {{ rtrim(rtrim((string) $quiz->passing_score_percent, '0'), '.') }}%.
            </p>
        </section>

        <section class="mt-8" aria-labelledby="quiz-review-heading">
            <h2 id="quiz-review-heading" class="text-xl font-semibold text-ink">Review your answers</h2>

            <ol class="mt-5 space-y-5" role="list">
                @foreach ($questions as $index => $question)
                    @php
                        $answer = $answers->get($question->id);
                        $selectedOption = $answer?->selectedOption;
                    @endphp
                    <li class="border border-line bg-surface p-5">
                        <h3 class="font-semibold text-ink">
                            <span class="font-mono text-xs text-ink-muted">Question {{ $index + 1 }}</span>
                            <span class="mt-1 block">{{ $question->prompt }}</span>
                        </h3>

                        <ul class="mt-3 space-y-2" role="list">
                            @foreach ($question->options as $option)
                                @php $isSelected = $selectedOption !== null && $selectedOption->id === $option->id; @endphp
                                <li class="flex flex-wrap items-center gap-2 rounded-md border px-4 py-2 text-sm {{ $option->is_correct ? 'border-success-text bg-success-surface text-success-text' : ($isSelected ? 'border-line bg-surface-muted text-ink' : 'border-line bg-surface text-ink-muted') }}">
                                    <span>{{ $option->option_text }}</span>
                                    @if ($option->is_correct)
                                        <span class="ml-auto text-xs font-semibold uppercase tracking-wide">Correct answer</span>
                                    @endif
                                    @if ($isSelected && ! $option->is_correct)
                                        <span class="ml-auto text-xs font-semibold uppercase tracking-wide">Your answer</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($question->explanation)
                            <p class="mt-3 border-l-4 border-line bg-surface-muted px-4 py-2 text-sm leading-6 text-ink-muted">{{ $question->explanation }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="mt-8 border-t border-line pt-6">
            @if ($attempt->passed)
                <p class="text-sm text-ink-muted">You passed this quiz. No further attempts are needed.</p>
            @else
                <p class="text-sm text-ink-muted">You can try again if you have attempts left.</p>
                <a href="{{ route('student.quizzes.show', [$course, $quiz]) }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Back to {{ $quiz->title }}</a>
            @endif
        </div>
    </div>
@endsection
