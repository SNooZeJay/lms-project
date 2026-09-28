@extends('layouts.app-shell')

@section('title', 'Edit lesson')
@section('workspace-context', 'Teaching workspace')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Course outline"
            :title="'Edit lesson '.$lesson->position"
            description="The lesson address, position, status, and parent module stay under server control."
        />

        <section class="card-muted mt-8 p-5" aria-labelledby="lesson-facts-heading">
            <h2 id="lesson-facts-heading" class="text-sm font-semibold text-ink">Fixed for this lesson</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2 sm:grid-cols-4">
                <div>
                    <dt class="meta-label">Position</dt>
                    <dd class="meta-value tabular-nums">{{ $lesson->position }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Status</dt>
                    <dd class="mt-1">
                        <x-status :value="$lesson->status->value" />
                    </dd>
                </div>
                <div>
                    <dt class="meta-label">Address</dt>
                    <dd class="mt-1 font-mono text-xs break-all text-ink">{{ $lesson->slug }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Materials</dt>
                    <dd class="meta-value tabular-nums">{{ $lesson->learningMaterials()->count() }}</dd>
                </div>
            </dl>
        </section>

        <x-form-errors :errors="$errors" class="mt-6" />

        <form
            method="POST"
            action="{{ route('instructor.courses.modules.lessons.update', [$course, $module, $lesson]) }}"
            class="card mt-6 space-y-6 p-5 sm:p-6"
            data-pending
        >
            @csrf
            @method('PATCH')

            <x-form-field
                name="title"
                label="Lesson title"
                :value="$lesson->title"
                :maxlength="160"
                hint="The lesson address stays the same after a title change, so existing links keep working."
                required
                autofocus
            />

            <x-form-field
                name="summary"
                label="Summary"
                type="textarea"
                :rows="3"
                :value="$lesson->summary"
                :maxlength="5000"
            />

            <x-form-field
                name="content_text"
                label="Lesson content"
                type="textarea"
                :rows="12"
                :value="$lesson->content_text"
                :maxlength="100000"
                hint="Leave a blank line between paragraphs. The lesson page turns each blank line into a new paragraph."
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <x-form-field
                    name="estimated_minutes"
                    label="Estimated minutes"
                    type="number"
                    :value="$lesson->estimated_minutes"
                    field-class="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                    hint="Optional. A positive number of minutes, or leave it empty."
                />

                {{-- A required lesson counts toward the completion percentage, so
                     the control explains that rather than just naming itself.

                     The hidden `0` comes first on purpose. A form sends its fields
                     in document order, and the last value for a repeated name is
                     the one the server keeps, so the unchecked state must be
                     submitted before the checkbox can override it. --}}
                <div>
                    <span class="field-label">Completion</span>
                    <input type="hidden" name="is_required" value="0">
                    <label
                        for="is_required"
                        class="mt-2 flex min-h-12 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink transition-colors hover:bg-surface has-checked:border-primary has-checked:bg-primary-quiet"
                    >
                        <input
                            id="is_required"
                            name="is_required"
                            value="1"
                            type="checkbox"
                            class="field-check"
                            @checked(old('is_required', $lesson->is_required))
                        >
                        <span>
                            <span class="block font-semibold">Required lesson</span>
                            <span class="block text-ink-muted">A required lesson counts toward course completion.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-btn :href="route('instructor.courses.show', $course)" variant="secondary" size="lg">
                    Cancel
                </x-btn>
                <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                    <span data-pending-text>Save lesson</span>
                </x-btn>
            </div>
        </form>
    </div>
@endsection
