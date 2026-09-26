@extends('layouts.app')

@section('title', 'Edit learning material')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to course outline</a>
        <p class="mt-8 font-mono text-sm font-semibold text-primary-text">{{ $course->title }} · {{ $module->title }} · {{ $lesson->title }}</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Edit learning material</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Only the metadata below can change. The position, uploader, and storage details stay under server control.</p>

        <x-form-errors :errors="$errors" />

        <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-line py-5 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-ink-muted">Position</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $material->position }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Type</dt>
                <dd class="mt-1 font-semibold text-ink">{{ ucfirst(str_replace('_', ' ', $material->material_type->value)) }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Uploader</dt>
                <dd class="mt-1 truncate font-semibold text-ink">{{ $material->uploader?->name ?? 'Unknown' }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Stored file</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $material->storage_path ? 'Yes' : 'None' }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('instructor.courses.materials.update', [$course, $module, $lesson, $material]) }}" class="mt-8 space-y-6">
            @csrf
            @method('PATCH')

            <div>
                <label for="title" class="block text-sm font-semibold text-ink">Material title <span class="text-error-text" aria-hidden="true">*</span></label>
                <input id="title" name="title" type="text" value="{{ old('title', $material->title) }}" required maxlength="255" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>

            <div>
                <label for="material_type" class="block text-sm font-semibold text-ink">Material type <span class="text-error-text" aria-hidden="true">*</span></label>
                <select id="material_type" name="material_type" required class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    @foreach (\App\Enums\LearningMaterialType::cases() as $type)
                        @continue(in_array($type->value, ['image', 'pdf', 'document'], true))
                        <option value="{{ $type->value }}" @selected(old('material_type', $material->material_type->value) === $type->value)>{{ ucfirst(str_replace('_', ' ', $type->value)) }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs leading-5 text-ink-muted">File types such as image, PDF, and document are not available yet.</p>
            </div>

            <div>
                <label for="content_text" class="block text-sm font-semibold text-ink">Material content</label>
                <textarea id="content_text" name="content_text" rows="8" maxlength="100000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('content_text', $material->content_text) }}</textarea>
                <p class="mt-2 text-xs leading-5 text-ink-muted">Required for Text and Code materials.</p>
            </div>

            <div>
                <label for="external_url" class="block text-sm font-semibold text-ink">Material link</label>
                <input id="external_url" name="external_url" type="url" inputmode="url" value="{{ old('external_url', $material->external_url) }}" maxlength="2048" placeholder="https://example.com/page" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                <p class="mt-2 text-xs leading-5 text-ink-muted">Required for Video link and External link materials.</p>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Cancel</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Save material</button>
            </div>
        </form>
    </div>
@endsection
