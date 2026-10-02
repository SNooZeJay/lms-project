{{--
    One hand-in, the brief it answers, and the control that decides what happens
    to it.

    The brief is above the work on purpose. An instructor deciding between a mark
    and a hand-back is deciding by comparing two things, and a page that shows only
    the submission makes them go and remember what was asked.

    THE CONTROL IS ONE FORM WITH TWO OUTCOMES, NOT TWO FORMS

    "Mark it" and "hand it back" are the same decision seen from either end, and
    two forms on one page would let both be filled in and one quietly win. So there
    is one form, one radio group naming the intent, and a mark box that the server
    requires for a mark and refuses for a hand-back. The wording in each option says
    what happens to the student, because that is the part people hesitate over.
--}}
@extends('layouts.app-shell')

@section('title', $submission->student->name)
@section('workspace-context', $course->title)

@section('content')
@section('measure', 'narrow')

    @php
        $reading = \App\Support\StatusLabel::forSubmission($submission->status);
        $canMark = $assignment->isMarkable();
    @endphp

    <x-page-header
        :eyebrow="$assignment->title"
        :title="$submission->student->name"
        :description="'Handed in '.$submission->submitted_at->format('j M Y, g:i a').' as '.$submission->original_name.'.'"
        level="1"
    >
        <x-slot:actions>
            <x-badge :tone="$reading['tone']">{{ $reading['label'] }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <x-form-errors :errors="$errors" class="mt-6" />

    {{-- The question, in full. --}}
    <section class="mt-8" aria-labelledby="brief-heading">
        <h2 id="brief-heading" class="text-lg font-[650] tracking-tight text-ink">
            What was asked
        </h2>

        <div class="prose-measure mt-3 space-y-5 leading-8 text-ink-muted">
            @foreach (preg_split('/\R{2,}/', $assignment->instructions) as $paragraph)
                @if (trim($paragraph) !== '')
                    <p>{{ trim($paragraph) }}</p>
                @endif
            @endforeach
        </div>
    </section>

    {{-- The work. Downloaded, never rendered: a file a student uploaded is
         arbitrary bytes, and serving it inline on this origin would share a
         session with the application. --}}
    <section class="mt-10" aria-labelledby="work-heading">
        <h2 id="work-heading" class="text-lg font-[650] tracking-tight text-ink">What was handed in</h2>

        <div class="mt-3 rounded-lg border border-line bg-surface p-5">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-subtle">File</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $submission->original_name }}</dd>
                </div>
                <div>
                    <dt class="text-ink-subtle">Size</dt>
                    <dd class="mt-0.5 text-ink">{{ number_format($submission->byte_size / 1024, 0) }} KB</dd>
                </div>
                <div>
                    <dt class="text-ink-subtle">Type</dt>
                    <dd class="mt-0.5 text-ink">{{ $submission->mime_type }}</dd>
                </div>
                <div>
                    <dt class="text-ink-subtle">Handed in</dt>
                    <dd class="mt-0.5 text-ink">{{ $submission->submitted_at->format('j M Y, g:i a') }}</dd>
                </div>
            </dl>

            <div class="mt-5">
                <x-btn
                    variant="primary"
                    size="md"
                    :href="route('instructor.assignments.submissions.download', $submission)"
                >
                    <x-icon name="download" size="sm" />
                    Download and open it
                </x-btn>
            </div>
        </div>

        <p class="mt-3 text-xs leading-6 text-ink-subtle">
            It downloads rather than opening in this page. A file a student uploaded is arbitrary
            content, and this application will not render it inside the page you are signed in to.
        </p>
    </section>

    {{-- Already marked, or handed back. Shown as a record, not as an editable
         form, because a mark already given is history and this page's job when it
         is set is to show what was decided. The form still appears underneath so
         a mark can be corrected. --}}
    @if ($submission->isGraded() && $submission->score !== null)
        <section class="mt-10" aria-labelledby="recorded-heading">
            <h2 id="recorded-heading" class="text-lg font-[650] tracking-tight text-ink">
                What you recorded
            </h2>

            <div class="mt-3 rounded-lg border border-line bg-surface p-5">
                <p class="text-2xl font-[650] text-ink">
                    {{ rtrim(rtrim(number_format((float) $submission->score, 2), '0'), '.') }}
                    <span class="text-base font-normal text-ink-subtle">out of {{ $assignment->max_score }}</span>
                    @if ($submission->percentage() !== null)
                        <span class="text-base font-normal text-ink-subtle">· {{ $submission->percentage() }}%</span>
                    @endif
                </p>

                @if ($submission->feedback)
                    <div class="mt-4 space-y-3 leading-7 text-ink-muted">
                        @foreach (preg_split('/\R{2,}/', $submission->feedback) as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p>{{ trim($paragraph) }}</p>
                            @endif
                        @endforeach
                    </div>
                @endif

                <p class="mt-4 text-xs text-ink-subtle">
                    Recorded {{ $submission->graded_at?->format('j M Y, g:i a') }}
                    @if ($submission->grader) by {{ $submission->grader->name }} @endif
                </p>
            </div>
        </section>
    @endif

    {{-- The decision. --}}
    <section class="mt-12" aria-labelledby="mark-heading">
        <h2 id="mark-heading" class="text-lg font-[650] tracking-tight text-ink">
            {{ $submission->isGraded() ? 'Change this' : 'Mark it' }}
        </h2>

        @unless ($canMark)
            <x-note tone="warning" class="mt-3">
                This assignment has no mark scale yet, so nothing can be marked. Set the scale on the
                assignment first, then come back.
            </x-note>
        @endunless

        @if ($canMark)
            <form
                method="POST"
                action="{{ route('instructor.courses.assignments.submissions.grade', [$course, $submission]) }}"
                class="mt-4 space-y-6 rounded-lg border border-line bg-surface p-5 sm:p-6"
                data-validate
            >
                @csrf

                <fieldset>
                    <legend class="field-label">What are you doing with this?</legend>

                    <div class="mt-3 space-y-3">
                        <label class="check items-start">
                            <input
                                type="radio"
                                name="intent"
                                value="grade"
                                @checked(old('intent', 'grade') === 'grade')
                            >
                            <span class="check-box" aria-hidden="true">
                                <x-icon name="check" size="xs" />
                            </span>
                            <span>
                                <span class="block text-sm font-medium text-ink">Give it a mark</span>
                                <span class="mt-0.5 block text-sm leading-6 text-ink-muted">
                                    The student sees the mark and your note straight away.
                                </span>
                            </span>
                        </label>

                        <label class="check items-start">
                            <input
                                type="radio"
                                name="intent"
                                value="return"
                                @checked(old('intent') === 'return')
                            >
                            <span class="check-box" aria-hidden="true">
                                <x-icon name="check" size="xs" />
                            </span>
                            <span>
                                <span class="block text-sm font-medium text-ink">Hand it back to be redone</span>
                                <span class="mt-0.5 block text-sm leading-6 text-ink-muted">
                                    No mark. The student sees your note and can upload again, which puts
                                    it back in your queue.
                                </span>
                            </span>
                        </label>
                    </div>

                    @error('intent')
                        <p class="field-error mt-2">{{ $message }}</p>
                    @enderror
                </fieldset>

                <x-form-field
                    name="score"
                    label="Mark"
                    type="number"
                    :min="0"
                    :max="$assignment->max_score"
                    inputmode="numeric"
                    :value="old('score', $submission->score !== null ? rtrim(rtrim(number_format((float) $submission->score, 2), '0'), '.') : '')"
                    :hint="'Whole numbers or decimals, from 0 to '.$assignment->max_score.'. Leave this empty when handing work back.'"
                />

                <x-form-field
                    name="feedback"
                    label="Note to the student"
                    type="textarea"
                    :rows="5"
                    :maxlength="5000"
                    :hint="$submission->isPending() ? 'Optional when marking, required when handing back.' : 'Say what was done well and what to change.'"
                />

                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                    <x-btn variant="primary" size="md" type="submit">Record this</x-btn>

                    <x-btn variant="quiet" size="md" :href="route('instructor.courses.assignments.show', [$course, $assignment])">
                        Back to the queue
                    </x-btn>
                </div>
            </form>
        @endif
    </section>

@endsection