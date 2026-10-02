{{--
    An instructor sets work against a lesson.

    Three things are asked for and two of them are optional, because a brief may
    be three sentences of text and nothing else. Nothing on this form is required
    that a person would reasonably not have: no due date, no mark scale, no
    attachment. The mark scale in particular is optional, and a brief can exist
    without one — the student can still hand in, and the instructor can fill the
    scale in before marking anything.

    The checkbox decides whether a student can see this, and it is checked by
    default because a form that saves nothing visible by default reads as broken
    to the person using it. Publishing is spelled out in the label rather than left
    as a bare "Publish" checkbox, because the consequence of getting it wrong is a
    brief nobody can reach.
--}}
@extends('layouts.app-shell')

@section('title', 'Set an assignment')
@section('workspace-context', $course->title)

@section('content')
@section('measure', 'narrow')

    <x-page-header
        :eyebrow="$lesson->title"
        title="Set an assignment"
        description="Ask for some work, optionally attach a briefing or a Google Form, and decide the mark scale now or later."
        level="1"
    />

    <x-form-errors :errors="$errors" class="mt-6" />

    <form
        method="POST"
        action="{{ route('instructor.courses.assignments.store', [$course, $lesson]) }}"
        enctype="multipart/form-data"
        class="mt-6 space-y-6 rounded-lg border border-line bg-surface p-5 sm:p-6"
        data-validate
    >
        @csrf

        <x-form-field
            name="title"
            label="What is this called?"
            required
            maxlength="180"
            placeholder="Week 3 reflection"
            hint="A title a student would recognise in a list."
        />

        <x-form-field
            name="instructions"
            label="What is being asked?"
            type="textarea"
            :rows="7"
            required
            :maxlength="20000"
            hint="The whole question, in your own words. A student should be able to answer from this without asking you."
        />

        <x-form-field
            name="form_url"
            label="Google Form link"
            type="url"
            maxlength="255"
            placeholder="https://docs.google.com/forms/d/e/..."
            hint="Optional. A Google Form opens in the student's new tab; it is not embedded here. Only docs.google.com/forms and forms.gle addresses are accepted."
        />

        <x-form-field
            name="briefing"
            label="Briefing document"
            type="file"
            accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.png,.jpg,.jpeg,.webp"
            field-class="file:mr-3 file:min-h-11 file:rounded-md file:border-0 file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-ink"
            hint="Optional, and a second way of saying the same thing in more room. Up to 10 MB. Accepted: {{ implode(', ', $briefingTypes) }}."
        />

        <x-form-field
            name="max_score"
            label="Mark scale"
            type="number"
            min="1"
            max="10000"
            inputmode="numeric"
            placeholder="100"
            hint="Optional, and in your own scale: 5, 10 or 100 all work. You cannot mark a submission until this is set. Leave it empty and set it before you start marking."
        />

        <x-form-field
            name="due_at"
            label="Hand in by"
            type="datetime-local"
            hint="Optional. After this moment the server refuses a hand-in, so a student cannot submit late by accident."
        />

        {{-- Publication is one checkbox whose label states the consequence.
             An unchecked box here is not a hidden default; the server reads this
             exact field and nothing else.

             The `.check` pattern rather than a raw input, so the box, the tick and
             the focus ring are the ones every other form in the application uses.
             A hand-rolled checkbox here would be the one control on the site whose
             keyboard behaviour nobody had checked. --}}
        <div class="rounded-md border border-line bg-surface-sunken p-4">
            <label class="check items-start">
                <input type="checkbox" name="publish" value="1" @checked(old('publish', true)) class="mt-1">
                <span class="check-box" aria-hidden="true">
                    <x-icon name="check" size="xs" />
                </span>
                <span>
                    <span class="block text-sm font-medium text-ink">Publish this so students can see it</span>
                    <span class="mt-1 block text-sm leading-6 text-ink-muted">
                        Leave this unticked to keep it as a draft. A draft is readable by you and by nobody
                        else, and can be published later.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
            <x-btn variant="primary" size="md" type="submit">Save assignment</x-btn>

            <x-btn
                variant="quiet"
                size="md"
                :href="route('instructor.courses.show', [$course])"
            >
                Cancel
            </x-btn>
        </div>
    </form>

    {{-- What the student will see, said plainly. A form that describes its own
         consequence is cheaper to use correctly than one that has to be
         discovered after a student hits it. --}}
    <x-note tone="neutral" class="mt-8">
        A student sees your instructions as paragraphs, your Google Form link, and a button to upload
        one file. After they hand in, their page says the work is submitted and waiting for checking
        or scoring. When you mark it, they see your mark and your note.
    </x-note>

@endsection