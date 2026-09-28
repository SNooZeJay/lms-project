@extends('layouts.app-shell')

@section('title', 'Edit module')
@section('workspace-context', 'Teaching workspace')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Course outline"
            :title="'Edit module '.$module->position"
            description="The position, status, and parent course stay under server control. This form changes the title and description of this module only."
        />

        <section class="card-muted mt-8 p-5" aria-labelledby="module-facts-heading">
            <h2 id="module-facts-heading" class="text-sm font-semibold text-ink">Fixed for this module</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2 sm:grid-cols-3">
                <div>
                    <dt class="meta-label">Position</dt>
                    <dd class="meta-value tabular-nums">{{ $module->position }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Status</dt>
                    <dd class="mt-1">
                        <x-status :value="$module->status->value" />
                    </dd>
                </div>
                <div>
                    <dt class="meta-label">Lessons</dt>
                    <dd class="meta-value tabular-nums">{{ $module->lessons()->count() }}</dd>
                </div>
            </dl>
        </section>

        <x-form-errors :errors="$errors" class="mt-6" />

        <form
            method="POST"
            action="{{ route('instructor.courses.modules.update', [$course, $module]) }}"
            class="card mt-6 space-y-6 p-5 sm:p-6"
            data-pending
        >
            @csrf
            @method('PATCH')

            <x-form-field
                name="title"
                label="Module title"
                :value="$module->title"
                :maxlength="160"
                required
                autofocus
            />

            <x-form-field
                name="description"
                label="Module description"
                type="textarea"
                :rows="4"
                :value="$module->description"
                :maxlength="5000"
            />

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-btn :href="route('instructor.courses.show', $course)" variant="secondary" size="lg">
                    Cancel
                </x-btn>
                <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                    <span data-pending-text>Save module</span>
                </x-btn>
            </div>
        </form>
    </div>
@endsection
