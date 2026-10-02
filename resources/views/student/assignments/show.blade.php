{{--
    A student reads the brief, hands in work, and sees where it got to.

    The state of their hand-in is the first thing on the page and not a footnote,
    because "did they get this yet" is the only reason a student comes back to
    this page a second time. Everything else here answers a question they have
    only on the first visit.

    ONE SUBMISSION, NOT A LIST

    There is one hand-in per assignment per student, and submitting again
    replaces it. That is stated in plain words before the upload control rather
    than discovered afterwards, because replacing someone's work without saying so
    is how a student loses an hour of writing.
--}}
@extends('layouts.app-shell')

@section('title', $assignment->title)
@section('workspace-context', $course->title)

@section('content')
@section('measure', 'narrow')

    @php
        $assignmentReading = \App\Support\StatusLabel::forAssignment($assignment->status);
    @endphp

    {{-- The state is in the header rather than only in the note further down,
         because a student who arrives to check whether they can still hand
         something in should not have to read a paragraph to find out. --}}
    <x-page-header
        :eyebrow="$lesson->title"
        :title="$assignment->title"
        level="1"
    >
        <x-slot:actions>
            <x-badge :tone="$assignmentReading['tone']">{{ $assignmentReading['label'] }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    {{-- Where the student's own work has got to. Read before the brief, because
         it is the answer to the question they arrived with. --}}
    <section class="mt-8" aria-labelledby="your-work-heading">
        <h2 id="your-work-heading" class="text-lg font-[650] tracking-tight text-ink">
            Your work
        </h2>

        @if ($submission === null)
            <x-note tone="neutral" class="mt-4">
                You have not handed anything in yet. You can upload one file, and you can replace it
                until your instructor has marked it.
            </x-note>
        @else
            @php
                $submissionReading = \App\Support\StatusLabel::forSubmission($submission->status);
            @endphp

            <div class="mt-4 rounded-lg border border-line bg-surface p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :tone="$submissionReading['tone']">{{ $submissionReading['label'] }}</x-badge>
                    <span class="text-xs text-ink-subtle">
                        handed in {{ $submission->submitted_at->format('j M Y, g:i a') }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-ink-subtle">File you handed in</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ $submission->original_name }}</dd>
                    </div>

                    @if ($submission->isGraded() && $submission->percentage() !== null)
                        <div>
                            <dt class="text-ink-subtle">Mark</dt>
                            <dd class="mt-0.5 text-lg font-[650] text-ink">
                                {{ rtrim(rtrim(number_format((float) $submission->score, 2), '0'), '.') }}
                                <span class="text-sm font-normal text-ink-subtle">
                                    out of {{ $assignment->max_score }}
                                </span>
                                <span class="ml-1 text-sm font-normal text-ink-subtle">
                                    ({{ $submission->percentage() }}%)
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($submission->feedback)
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="text-sm font-medium text-ink">Note from your instructor</p>
                        {{-- Blank lines become paragraphs, because feedback written
                             as two paragraphs is not one sentence. --}}
                        <div class="mt-2 space-y-3 leading-7 text-ink-muted">
                            @foreach (preg_split('/\R{2,}/', $submission->feedback) as $paragraph)
                                @if (trim($paragraph) !== '')
                                    <p>{{ trim($paragraph) }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($submission->graded_at === null && $submission->feedback === null && ! $submission->isPending())
                    <x-note tone="warning" class="mt-4">
                        Handed back to be redone. Your instructor's note is above.
                    </x-note>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    <x-btn
                        variant="secondary"
                        size="sm"
                        :href="route('student.assignments.submissions.download', $submission)"
                    >
                        Download what I handed in
                    </x-btn>
                </div>
            </div>
        @endif
    </section>

    {{-- The brief. --}}
    <section class="mt-10" aria-labelledby="brief-heading">
        <h2 id="brief-heading" class="text-lg font-[650] tracking-tight text-ink">
            What is being asked
        </h2>

        <div class="prose-measure mt-4 space-y-5 leading-8 text-ink">
            @foreach (preg_split('/\R{2,}/', $assignment->instructions) as $paragraph)
                @if (trim($paragraph) !== '')
                    <p>{{ trim($paragraph) }}</p>
                @endif
            @endforeach
        </div>

        {{-- What it is marked out of, before it is handed in.

             A student deciding how much effort a piece of work is worth cannot do
             that without the number, and finding out the scale after handing in is
             the worst order to find out in. It sits under the question rather than
             in the header because it qualifies the question.

             Shown only when there is a scale. A brief with no scale says nothing
             here rather than promising something, and the instructor page is where
             that gap is explained. --}}
        @if ($assignment->isMarkable())
            <p class="mt-5 text-sm text-ink-muted">
                Marked out of
                <span class="font-medium text-ink">{{ $assignment->max_score }}</span>.
                @if ($submission !== null && $submission->percentage() !== null)
                    You were given {{ $submission->percentage() }}%.
                @endif
            </p>
        @endif

        @if ($assignment->due_at)
            <p class="mt-5 text-sm text-ink-muted">
                Hand in by
                <span class="font-medium text-ink">{{ $assignment->due_at->format('j M Y, g:i a') }}</span>.
            </p>
        @endif

        {{-- The two ways an instructor can add to a brief, and nothing else.

             A Google Form is a link out. It is not embedded, because embedding a
             form would mean framing a third party, and a framed third party is a
             hole in the content security policy this application is built on. The
             link opens in a new tab and the form runs on Google's own origin,
             which is where it has to run anyway to collect responses. --}}
        @if ($assignment->form_url || $assignment->briefing_path)
            <div class="mt-6 flex flex-wrap gap-3">
                @if ($assignment->form_url)
                    <a
                        href="{{ $assignment->form_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn btn-secondary btn-md"
                    >
                        Open the Google Form
                        <span class="sr-only"> (opens in a new tab)</span>
                    </a>
                @endif

                @if ($assignment->briefing_path)
                    <x-btn
                        variant="secondary"
                        size="md"
                        :href="route('student.assignments.briefing', $assignment)"
                    >
                        Download the briefing
                    </x-btn>
                @endif
            </div>

            <p class="mt-3 text-xs text-ink-subtle">
                The Google Form opens in a new tab. If you answer there, submit a file below as well so
                your instructor has something to mark.
            </p>
        @endif
    </section>

    {{-- Handing in. --}}
    <section class="mt-12" aria-labelledby="submit-heading">
        <h2 id="submit-heading" class="text-lg font-[650] tracking-tight text-ink">
            {{ $submission === null ? 'Hand in your work' : 'Replace what you handed in' }}
        </h2>

        @if ($assignment->status === \App\Models\Assignment::CLOSED)
            <x-note tone="neutral" class="mt-4">
                This assignment is closed. Your instructor is not accepting any more work against it.
                Ask them if you think that is wrong.
            </x-note>
        @elseif ($assignment->due_at && $assignment->due_at->isPast())
            <x-note tone="warning" class="mt-4">
                The date for this assignment has passed, so the server will refuse a hand-in. Ask your
                instructor if you need more time.
            </x-note>
        @else
            <x-form-errors :errors="$errors" class="mt-4" />

            <form
                method="POST"
                action="{{ route('student.assignments.submit', [$course, $lesson, $assignment]) }}"
                enctype="multipart/form-data"
                class="mt-4 space-y-5 rounded-lg border border-line bg-surface p-5"
                data-validate
            >
                @csrf

                {{-- The accepted list is built from the rule rather than written out
                     here, so the page cannot promise a file the server will refuse. --}}
                <x-form-field
                    name="submission"
                    label="Your file"
                    type="file"
                    required
                    accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.png,.jpg,.jpeg,.webp"
                    field-class="file:mr-3 file:min-h-11 file:rounded-md file:border-0 file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-ink"
                    hint="A PDF, Word document, plain text file, or a picture of your work. Up to {{ round($maxKilobytes / 1024) }} MB. Accepted: {{ implode(', ', $accepted) }}."
                />

                @if ($submission !== null)
                    <x-note tone="neutral">
                        Uploading again replaces what is above. Only your latest file will be marked.
                    </x-note>
                @endif

                <x-btn variant="primary" size="md" type="submit">
                    {{ $submission === null ? 'Hand in my work' : 'Replace my work' }}
                </x-btn>
            </form>

            {{-- "Waiting for checking" is said out loud, here, before they press
                 the button, because a student who does not know what happens next
                 will submit twice. --}}
            <p class="mt-3 text-xs leading-6 text-ink-subtle">
                After you hand in, this page will say your work is submitted and waiting for checking or
                scoring. Your instructor reads it and gives you a mark and a note.
            </p>
        @endif
    </section>

    <div class="mt-12">
        <x-btn variant="quiet" size="sm" :href="route('student.lessons.show', [$course, $lesson])">
            Back to the lesson
        </x-btn>
    </div>

@endsection