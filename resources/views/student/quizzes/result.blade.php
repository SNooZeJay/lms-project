@extends('layouts.app-shell')

@section('title', 'Quiz result')
@section('workspace-context', $course->title)

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'My courses', 'href' => route('student.courses.index')],
            ['label' => $course->title, 'href' => route('student.courses.show', $course)],
            ['label' => $quiz->title, 'href' => route('student.quizzes.show', [$course, $quiz])],
            ['label' => 'Result'],
        ]" class="mb-6" />

        <header class="border-b border-line pb-8">
            <p class="eyebrow">Attempt {{ $attempt->attempt_number }} result</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                {{ $quiz->title }}
            </h1>
        </header>

        {{-- The outcome. The score is a percentage plus points earned out of
             points available, and the pass state is a word, not a colour. --}}
        <section class="mt-8" aria-labelledby="quiz-score-heading">
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="quiz-score-heading" class="text-xl font-semibold text-ink">
                        {{ $attempt->passed ? 'Passed' : 'Not passed' }}
                    </h2>
                    <x-status :value="$attempt->status->value" />
                </div>

                <p class="mt-3 text-lg font-semibold text-ink tabular-nums">
                    {{ rtrim(rtrim((string) $attempt->score_percent, '0'), '.') }}%
                </p>
                <p class="mt-1 text-sm leading-6 text-ink-muted">
                    {{ rtrim(rtrim((string) $attempt->score_points, '0'), '.') }} of
                    {{ rtrim(rtrim((string) $attempt->total_points, '0'), '.') }} points.
                    Passing needs {{ rtrim(rtrim((string) $quiz->passing_score_percent, '0'), '.') }}%.
                </p>
            </div>
        </section>

        <section class="mt-8" aria-labelledby="quiz-review-heading">
            <h2 id="quiz-review-heading" class="text-xl font-semibold text-ink">Review your answers</h2>
            <p class="mt-1 text-sm leading-6 text-ink-muted">
                Each option is labelled in words, so the result reads the same without colour.
            </p>

            <ol role="list" class="mt-5 space-y-4">
                @foreach ($questions as $index => $question)
                    @php
                        $answer = $answers->get($question->id);
                        $selectedOption = $answer?->selectedOption;
                    @endphp
                    <li class="card p-5">
                        <h3 class="font-semibold text-ink">
                            <span class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                Question {{ $index + 1 }}
                            </span>
                            <span class="mt-1 block leading-7">{{ $question->prompt }}</span>
                        </h3>

                        <ul role="list" class="mt-4 space-y-2">
                            @foreach ($question->options as $option)
                                @php
                                    $isSelected = $selectedOption !== null && $selectedOption->id === $option->id;
                                    $isCorrectChoice = $option->is_correct;
                                @endphp
                                <li
                                    class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border px-4 py-3 text-sm leading-6
                                        {{ $isCorrectChoice
                                            ? 'border-success-line bg-success-surface text-success-text'
                                            : ($isSelected
                                                ? 'border-warning-line bg-warning-surface text-warning-text'
                                                : 'border-line bg-surface-muted text-ink-muted') }}"
                                >
                                    <x-icon
                                        :name="$isCorrectChoice ? 'check-circle' : ($isSelected ? 'x-circle' : 'info')"
                                        size="sm"
                                        class="shrink-0"
                                    />
                                    <span class="min-w-0 flex-1">{{ $option->option_text }}</span>

                                    @if ($isCorrectChoice)
                                        <span class="shrink-0 text-xs font-semibold">Correct answer</span>
                                    @endif

                                    @if ($isSelected)
                                        <span class="shrink-0 text-xs font-semibold">
                                            {{ $isCorrectChoice ? 'Your answer, and correct' : 'Your answer' }}
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($question->explanation)
                            <x-note tone="info" class="mt-4">
                                <span class="font-semibold">Why:</span> {{ $question->explanation }}
                            </x-note>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="mt-8 border-t border-line pt-6">
            @if ($attempt->passed)
                <p class="text-sm leading-6 text-ink-muted">
                    You passed this quiz, so no further attempts are needed. If this quiz is required for the
                    course, it now counts toward your certificate.
                </p>
            @else
                <p class="text-sm leading-6 text-ink-muted">
                    You can try again while you have attempts left. The quiz page always shows how many you have
                    used.
                </p>
            @endif

            <x-btn :href="route('student.quizzes.show', [$course, $quiz])" variant="primary" size="lg" class="mt-4">
                <x-icon name="arrow-left" size="sm" />
                Back to {{ $quiz->title }}
            </x-btn>
        </div>
    </div>
@endsection
