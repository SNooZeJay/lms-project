@extends('layouts.app-shell')

@section('title', 'Attempt '.$attempt->attempt_number)
@section('workspace-context', $quiz->title)

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'My courses', 'href' => route('student.courses.index')],
            ['label' => $course->title, 'href' => route('student.courses.show', $course)],
            ['label' => $quiz->title, 'href' => route('student.quizzes.show', [$course, $quiz])],
            ['label' => 'Attempt '.$attempt->attempt_number],
        ]" class="mb-6" />

        <x-form-errors :errors="$errors" class="mb-6" />

        <header class="border-b border-line pb-8">
            <p class="eyebrow">Attempt {{ $attempt->attempt_number }} of {{ $quiz->max_attempts }}</p>
            <h1 class="mt-2 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                {{ $quiz->title }}
            </h1>

            @if ($quiz->instructions)
                <x-note tone="neutral" class="mt-5">{{ $quiz->instructions }}</x-note>
            @endif
        </header>

        <form
            method="POST"
            action="{{ route('student.quizzes.attempts.submit', [$course, $quiz, $attempt]) }}"
            class="mt-8 space-y-5"
            data-pending
        >
            @csrf

            <ol role="list" class="space-y-4">
                @foreach ($questions as $index => $question)
                    <li class="card p-5">
                        <fieldset>
                            <legend class="font-semibold text-ink">
                                <span class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                    Question {{ $index + 1 }}
                                </span>
                                <span class="mt-1 block leading-7">{{ $question->prompt }}</span>
                            </legend>

                            {{-- One option per row, each a large tap target with a
                                 real radio input, so the whole row is the
                                 control and a selected state is visible. --}}
                            <div class="mt-4 space-y-2">
                                @foreach ($question->options as $option)
                                    <label
                                        for="answer-{{ $question->id }}-{{ $option->id }}"
                                        class="flex min-h-12 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink transition-colors has-checked:border-primary has-checked:bg-primary-quiet hover:bg-surface focus-within:ring-3 focus-within:ring-focus"
                                    >
                                        <input
                                            id="answer-{{ $question->id }}-{{ $option->id }}"
                                            type="radio"
                                            name="answers[{{ $question->id }}]"
                                            value="{{ $option->id }}"
                                            required
                                            @checked((int) ($selected[$question->id] ?? 0) === $option->id)
                                            class="field-check"
                                        >
                                        <span class="font-mono text-xs text-ink-subtle" aria-hidden="true">
                                            {{ chr(65 + $loop->index) }}.
                                        </span>
                                        <span>{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </li>
                @endforeach
            </ol>

            <div class="card p-5">
                <p class="text-sm leading-6 text-ink-muted">
                    Submitting grades this attempt on the server. Answers cannot be changed afterwards, and the
                    correct answer is not shown until you see the result.
                </p>

                <x-btn type="submit" variant="primary" size="lg" class="mt-4" data-pending-button>
                    <x-icon name="check" size="sm" />
                    <span data-pending-text>Submit attempt {{ $attempt->attempt_number }}</span>
                </x-btn>
            </div>
        </form>
    </div>
@endsection
