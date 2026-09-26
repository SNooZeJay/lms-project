@extends('layouts.app')

@section('title', 'Edit lesson')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to course outline</a>
        <p class="mt-8 font-mono text-sm font-semibold text-primary-text">{{ $course->title }} · {{ $module->title }}</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Edit lesson</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">The Lesson address, position, status, and parent Module stay under server control.</p>

        <x-form-errors :errors="$errors" />

        <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-line py-5 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-ink-muted">Position</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $lesson->position }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Status</dt>
                <dd class="mt-1 font-semibold text-ink">{{ ucfirst($lesson->status->value) }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Address</dt>
                <dd class="mt-1 break-all font-mono text-xs text-ink">{{ $lesson->slug }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Materials</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $lesson->learningMaterials()->count() }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('instructor.courses.modules.lessons.update', [$course, $module, $lesson]) }}" class="mt-8 space-y-6">
            @csrf
            @method('PATCH')

            <div>
                <label for="title" class="block text-sm font-semibold text-ink">Lesson title <span class="text-error-text" aria-hidden="true">*</span></label>
                <input id="title" name="title" type="text" value="{{ old('title', $lesson->title) }}" required maxlength="160" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                <p class="mt-2 text-xs leading-5 text-ink-muted">The lesson address stays the same after a title change.</p>
            </div>

            <div>
                <label for="summary" class="block text-sm font-semibold text-ink">Summary</label>
                <textarea id="summary" name="summary" rows="3" maxlength="5000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('summary', $lesson->summary) }}</textarea>
            </div>

            <div>
                <label for="content_text" class="block text-sm font-semibold text-ink">Lesson content</label>
                <textarea id="content_text" name="content_text" rows="10" maxlength="100000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('content_text', $lesson->content_text) }}</textarea>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="estimated_minutes" class="block text-sm font-semibold text-ink">Estimated minutes</label>
                    <input id="estimated_minutes" name="estimated_minutes" type="number" min="1" step="1" value="{{ old('estimated_minutes', $lesson->estimated_minutes) }}" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                </div>
                <label class="flex min-h-11 items-center gap-3 self-end rounded-md border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink">
                    <input type="hidden" name="is_required" value="0">
                    <input name="is_required" value="1" type="checkbox" @checked(old('is_required', $lesson->is_required)) class="h-4 w-4 rounded border-line text-primary focus:ring-focus">
                    Required lesson
                </label>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Cancel</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Save lesson</button>
            </div>
        </form>
    </div>
@endsection
