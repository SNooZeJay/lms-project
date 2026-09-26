@extends('layouts.app-shell')

@section('title', 'Edit learning material')
@section('workspace-context', 'Teaching workspace')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'My courses', 'href' => route('instructor.courses.index')],
            ['label' => $course->title, 'href' => route('instructor.courses.show', $course)],
            ['label' => 'Edit material'],
        ]" class="mb-6" />

        <x-page-header
            eyebrow="Course outline"
            title="Edit learning material"
            description="Only the metadata below can change. The position, uploader, and storage details stay under server control."
        />

        <section class="card-muted mt-8 p-5" aria-labelledby="material-facts-heading">
            <h2 id="material-facts-heading" class="text-sm font-semibold text-ink">Fixed for this material</h2>

            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="meta-label">Position</dt>
                    <dd class="meta-value tabular-nums">{{ $material->position }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Type</dt>
                    <dd class="meta-value">{{ \App\Support\StatusLabel::words($material->material_type->value) }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Uploader</dt>
                    <dd class="meta-value">{{ $material->uploader?->name ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Stored file</dt>
                    <dd class="meta-value">{{ $material->storage_path ? 'Yes' : 'None' }}</dd>
                </div>
            </dl>
        </section>

        <x-form-errors :errors="$errors" class="mt-6" />

        <form
            method="POST"
            action="{{ route('instructor.courses.materials.update', [$course, $module, $lesson, $material]) }}"
            class="card mt-6 space-y-6 p-5 sm:p-6"
            data-pending
        >
            @csrf
            @method('PATCH')

            <x-form-field
                name="title"
                label="Material title"
                :value="$material->title"
                :maxlength="255"
                required
                autofocus
            />

            {{-- Only the four types the server accepts. Image, PDF, and document
                 are left out on purpose, because file uploads are not built. --}}
            <x-form-field
                name="material_type"
                label="Material type"
                type="select"
                :value="$material->material_type->value"
                required
                :options="collect(\App\Enums\LearningMaterialType::cases())
                    ->reject(fn (\App\Enums\LearningMaterialType $type): bool => in_array($type->value, ['image', 'pdf', 'document'], true))
                    ->mapWithKeys(fn (\App\Enums\LearningMaterialType $type): array => [
                        $type->value => \App\Support\StatusLabel::words($type->value),
                    ])
                    ->all()"
                hint="Text, Code, Video link, and External link are available. File types are not offered yet."
            />

            <x-form-field
                name="content_text"
                label="Material content"
                type="textarea"
                :rows="8"
                :value="$material->content_text"
                :maxlength="100000"
                field-class="font-mono"
                hint="Required for a Text or Code material."
            />

            <x-form-field
                name="external_url"
                label="Material link"
                type="url"
                :value="$material->external_url"
                inputmode="url"
                :maxlength="2048"
                placeholder="https://example.com/page"
                hint="Required for a Video link or External link material."
            />

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-btn :href="route('instructor.courses.show', $course)" variant="secondary" size="lg">
                    Cancel
                </x-btn>
                <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                    <span data-pending-text>Save material</span>
                </x-btn>
            </div>
        </form>
    </div>
@endsection
