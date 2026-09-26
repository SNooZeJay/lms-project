@extends('layouts.app')

@section('title', 'Attempt '.$attempt->attempt_number)

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.quizzes.show', [$course, $quiz]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to {{ $quiz->title }}</a>

        <x-form-errors :errors="$errors" />

        <header class="mt-6">
            <p class="font-mono text-sm font-semibold text-primary-text">Attempt {{ $attempt->attempt_number }} of {{ $quiz->max_attempts }}</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $quiz->title }}</h1>
            @if ($quiz->instructions)
                <p class="mt-3 max-w-2xl leading-7 text-ink-muted">{{ $quiz->instructions }}</p>
            @endif
        </header>

        <form method="POST" action="{{ route('student.quizzes.attempts.submit', [$course, $quiz, $attempt]) }}" class="mt-8 space-y-6">
            @csrf

            <ol class="space-y-5" role="list">
                @foreach ($questions as $index => $question)
                    <li class="border border-line bg-surface p-5">
                        <fieldset>
                            <legend class="font-semibold text-ink">
                                <span class="font-mono text-xs text-ink-muted">Question {{ $index + 1 }}</span>
                                <span class="mt-1 block">{{ $question->prompt }}</span>
                            </legend>

                            <div class="mt-4 space-y-2">
                                @foreach ($question->options as $option)
                                    <label for="answer-{{ $question->id }}-{{ $option->id }}" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface-muted px-4 py-2 text-sm text-ink transition-colors hover:bg-surface">
                                        <input id="answer-{{ $question->id }}-{{ $option->id }}" type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" required @checked((int) ($selected[$question->id] ?? 0) === $option->id) class="h-4 w-4 border-line text-primary focus:ring-focus">
                                        <span>{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </li>
                @endforeach
            </ol>

            <div class="border-t border-line pt-6">
                <p class="text-sm text-ink-muted">Submitting grades this attempt. Answers cannot be changed afterwards.</p>
                <button type="submit" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Submit attempt {{ $attempt->attempt_number }}</button>
            </div>
        </form>
    </div>
@endsection
