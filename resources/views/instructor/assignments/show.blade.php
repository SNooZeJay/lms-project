{{--
    One assignment, and everything handed in against it.

    The page answers one question — what still needs me — so pending work is first,
    oldest first, and marked work is below it under its own heading rather than
    mixed in. A mixed list makes that question slower to answer, and the number of
    students waiting is the only number here that a person would act on.

    A returned submission is in the second list, not the first, because it has
    been looked at. Putting it back in the queue would say it has not.
--}}
@extends('layouts.app-shell')

@section('title', $assignment->title)
@section('workspace-context', $course->title)

@section('content')

    @php
        $assignmentReading = \App\Support\StatusLabel::forAssignment($assignment->status);
    @endphp

    <x-page-header
        :eyebrow="$assignment->lesson->title"
        :title="$assignment->title"
        :description="'Set on '.$assignment->created_at->format('j M Y').' by '.$assignment->author->name.'.'"
        level="1"
    >
        <x-slot:actions>
            <x-badge :tone="$assignmentReading['tone']">{{ $assignmentReading['label'] }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    {{-- The publish/close control. One form, one question. Closing does not
         delete anything: the work already handed in stays readable and marked. --}}
    @if ($assignment->status !== \App\Models\Assignment::CLOSED)
        <form
            method="POST"
            action="{{ route('instructor.courses.assignments.status', [$course, $assignment]) }}"
            class="mt-6"
        >
            @csrf
            @method('PATCH')

            <x-btn
                variant="{{ $assignment->isPublished() ? 'secondary' : 'primary' }}"
                size="sm"
                type="submit"
            >
                {{ $assignment->isPublished() ? 'Close to new submissions' : 'Publish to students' }}
            </x-btn>
        </form>
    @else
        <x-note tone="neutral" class="mt-6">
            Closed. Students can still read this brief and download what they handed in, but nothing new
            will be accepted.
        </x-note>
    @endif

    {{-- The brief, in a short column. An instructor reading their own brief is
         checking what the students were told, and that is a reading task. --}}
    <section class="mt-10" aria-labelledby="brief-heading">
        <h2 id="brief-heading" class="text-lg font-[650] tracking-tight text-ink">The brief</h2>

        <div class="prose-measure mt-4 space-y-5 leading-8 text-ink-muted">
            @foreach (preg_split('/\R{2,}/', $assignment->instructions) as $paragraph)
                @if (trim($paragraph) !== '')
                    <p>{{ trim($paragraph) }}</p>
                @endif
            @endforeach
        </div>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Mark scale</dt>
                <dd class="mt-1 text-sm text-ink">
                    @if ($assignment->isMarkable())
                        Out of {{ $assignment->max_score }}
                    @else
                        <span class="text-ink-muted">Not set — you cannot mark yet</span>
                    @endif
                </dd>
            </div>

            @if ($assignment->due_at)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Hand in by</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $assignment->due_at->format('j M Y, g:i a') }}</dd>
                </div>
            @endif

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Handed in</dt>
                <dd class="mt-1 text-sm text-ink">
                    {{ $assignment->submissions->count() }}
                    {{ Str::plural('student', $assignment->submissions->count()) }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Waiting</dt>
                <dd class="mt-1 text-sm font-medium text-ink">{{ $pending->count() }}</dd>
            </div>
        </dl>

        @if ($assignment->form_url || $assignment->briefing_path)
            <div class="mt-6 flex flex-wrap gap-3">
                @if ($assignment->form_url)
                    <a href="{{ $assignment->form_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                        Open the Google Form
                        <span class="sr-only"> (opens in a new tab)</span>
                    </a>
                @endif

                @if ($assignment->briefing_path)
                    <x-btn variant="secondary" size="sm" :href="route('instructor.assignments.briefing.download', $assignment)">
                        Download the briefing
                    </x-btn>
                @endif
            </div>
        @endif
    </section>

    {{-- What needs doing. --}}
    <section class="mt-12" aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="text-lg font-[650] tracking-tight text-ink">
            Waiting for you
        </h2>

        @if ($pending->isEmpty())
            <x-empty-state
                class="mt-4"
                title="Nothing is waiting"
                description="{{ $assignment->submissions->isEmpty() ? 'Nobody has handed anything in against this assignment yet.' : 'Everything handed in against this assignment has been looked at.' }}"
                icon="clipboard"
                compact
            />
        @else
            <ul class="mt-4 divide-y divide-line rounded-lg border border-line bg-surface">
                @foreach ($pending as $submission)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink">{{ $submission->student->name }}</p>
                            <p class="truncate text-xs text-ink-subtle">
                                {{ $submission->original_name }}
                                · handed in {{ $submission->submitted_at->diffForHumans() }}
                            </p>
                        </div>

                        <x-btn
                            variant="secondary"
                            size="sm"
                            :href="route('instructor.courses.assignments.submissions.show', [$course, $submission])"
                        >
                            Open and mark
                        </x-btn>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- What has been looked at. --}}
    @if ($marked->isNotEmpty())
        <section class="mt-12" aria-labelledby="marked-heading">
            <h2 id="marked-heading" class="text-lg font-[650] tracking-tight text-ink">
                Already looked at
            </h2>

            <ul class="mt-4 divide-y divide-line rounded-lg border border-line bg-surface">
                @foreach ($marked as $submission)
                    @php
                        $reading = \App\Support\StatusLabel::forSubmission($submission->status);
                    @endphp

                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink">{{ $submission->student->name }}</p>
                            <p class="truncate text-xs text-ink-subtle">
                                {{ $submission->original_name }}
                                @if ($submission->isGraded() && $submission->percentage() !== null)
                                    · {{ rtrim(rtrim(number_format((float) $submission->score, 2), '0'), '.') }} / {{ $assignment->max_score }}
                                @endif
                            </p>
                        </div>

                        <x-badge :tone="$reading['tone']">{{ $reading['label'] }}</x-badge>

                        <x-btn
                            variant="quiet"
                            size="sm"
                            :href="route('instructor.courses.assignments.submissions.show', [$course, $submission])"
                        >
                            Open
                        </x-btn>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-12 flex flex-wrap gap-3">
        <x-btn variant="quiet" size="sm" :href="route('instructor.courses.show', [$course])">
            Back to the course
        </x-btn>
    </div>

@endsection