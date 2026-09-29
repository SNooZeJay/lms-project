@extends('layouts.app-shell')

@section('title', 'Create course')
@section('workspace-context', 'Teaching workspace')

@section('content')
@section('measure', 'medium')
            <x-page-header
            eyebrow="Instructor workspace"
            title="Create course"
            description="A new course starts as a private draft that only you can see. Publish it after it has at least one module and one lesson."
        />

        <x-form-errors :errors="$errors" class="mt-6" />

        <form
            method="POST"
            action="{{ route('instructor.courses.store') }}"
            class="card mt-8 space-y-6 p-5 sm:p-6"
            data-pending
        >
            @csrf

            <x-form-field
                name="title"
                label="Course title"
                :maxlength="160"
                placeholder="Networking Basics"
                hint="This is the name students see in the catalog."
                required
                autofocus
            />

            <x-form-field
                name="description"
                label="Description"
                type="textarea"
                :rows="4"
                :maxlength="5000"
                hint="A short summary of what the course covers."
            />

            <x-form-field
                name="learning_objectives"
                label="Learning objectives"
                type="textarea"
                :rows="4"
                :maxlength="5000"
                hint="What a student will be able to do after finishing."
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <x-form-field
                    name="category"
                    label="Category"
                    :maxlength="100"
                    placeholder="Networking"
                />

                <x-form-field
                    name="level"
                    label="Level"
                    type="select"
                    required
                    :options="\App\Support\StatusLabel::selectOptions(\App\Enums\CourseLevel::class)"
                />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <x-form-field
                    name="course_type"
                    label="Course type"
                    type="select"
                    required
                    :options="\App\Support\StatusLabel::selectOptions(\App\Enums\CourseType::class)"
                    hint="A free course costs nothing. A paid course asks for payment before it opens."
                />

                {{-- The price is stored as integer minor units, so the field asks
                     for centavos and the help text names the conversion. --}}
                <x-form-field
                    name="price_minor"
                    label="Price in centavos"
                    type="number"
                    field-class="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                    hint="100 centavos is ₱1.00. A free course must use 0."
                    required
                />
            </div>

            <x-note tone="info">
                Slug, currency, status, publication time, and price ownership are set by the server. This form
                cannot change them.
            </x-note>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-btn :href="route('instructor.courses.index')" variant="secondary" size="lg">
                    Cancel
                </x-btn>
                <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                    <span data-pending-text>Create private draft</span>
                </x-btn>
            </div>
        </form>
@endsection
