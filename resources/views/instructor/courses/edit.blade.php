@extends('layouts.app-shell')

@section('title', 'Edit course')
@section('workspace-context', 'Teaching workspace')

@section('content')
@section('measure', 'medium')
            <x-page-header
            eyebrow="Instructor workspace"
            title="Edit course"
            description="Only the details below can change. The owner, address, status, and publication time stay under server control."
        />

        {{-- The read only facts, shown before the form so an Instructor can see
             what the server owns. --}}
        <section class="card-muted mt-8 p-5" aria-labelledby="read-only-facts-heading">
            <h2 id="read-only-facts-heading" class="text-sm font-semibold text-ink">Fixed for this course</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2 sm:grid-cols-4">
                <div>
                    <dt class="meta-label">Status</dt>
                    <dd class="mt-1">
                        <x-status :value="$course->status->value" />
                    </dd>
                </div>
                <div>
                    <dt class="meta-label">Address</dt>
                    <dd class="mt-1 font-mono text-xs break-all text-ink">{{ $course->slug }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Modules</dt>
                    <dd class="meta-value tabular-nums">{{ $course->modules()->count() }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Currency</dt>
                    <dd class="meta-value">{{ $course->currency }}</dd>
                </div>
            </dl>
        </section>

        <x-form-errors :errors="$errors" class="mt-6" />

        <form
            method="POST"
            action="{{ route('instructor.courses.update', $course) }}"
            class="card mt-6 space-y-6 p-5 sm:p-6"
            data-pending
        >
            @csrf
            @method('PATCH')

            <x-form-field
                name="title"
                label="Course title"
                :value="$course->title"
                :maxlength="160"
                hint="The course address stays the same after a title change, so existing links keep working."
                required
                autofocus
            />

            <x-form-field
                name="description"
                label="Description"
                type="textarea"
                :rows="4"
                :value="$course->description"
                :maxlength="5000"
            />

            <x-form-field
                name="learning_objectives"
                label="Learning objectives"
                type="textarea"
                :rows="4"
                :value="$course->learning_objectives"
                :maxlength="5000"
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <x-form-field
                    name="category"
                    label="Category"
                    :value="$course->category"
                    :maxlength="100"
                />

                <x-form-field
                    name="level"
                    label="Level"
                    type="select"
                    :value="$course->level->value"
                    required
                    :options="\App\Support\StatusLabel::selectOptions(\App\Enums\CourseLevel::class)"
                />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <x-form-field
                    name="course_type"
                    label="Course type"
                    type="select"
                    :value="$course->course_type->value"
                    required
                    :options="\App\Support\StatusLabel::selectOptions(\App\Enums\CourseType::class)"
                    hint="Changing the type does not change the status. Publishing is a separate action."
                />

                <x-form-field
                    name="price_minor"
                    label="Price in centavos"
                    type="number"
                    :value="$course->price_minor"
                    field-class="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                    hint="100 centavos is ₱1.00. A free course must use 0."
                    required
                />
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-btn :href="route('instructor.courses.show', $course)" variant="secondary" size="lg">
                    Cancel
                </x-btn>
                <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                    <span data-pending-text>Save course details</span>
                </x-btn>
            </div>
        </form>
@endsection
