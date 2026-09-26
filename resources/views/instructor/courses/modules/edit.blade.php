@extends('layouts.app')

@section('title', 'Edit module')

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to course outline</a>
        <p class="mt-8 font-mono text-sm font-semibold text-primary-text">{{ $course->title }}</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Edit module</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">The Module position, status, and parent Course stay under server control.</p>

        <x-form-errors :errors="$errors" />

        <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-line py-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-ink-muted">Position</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $module->position }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Status</dt>
                <dd class="mt-1 font-semibold text-ink">{{ ucfirst($module->status->value) }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Lessons</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $module->lessons()->count() }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('instructor.courses.modules.update', [$course, $module]) }}" class="mt-8 space-y-6">
            @csrf
            @method('PATCH')

            <div>
                <label for="title" class="block text-sm font-semibold text-ink">Module title <span class="text-error-text" aria-hidden="true">*</span></label>
                <input id="title" name="title" type="text" value="{{ old('title', $module->title) }}" required maxlength="160" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-ink">Module description</label>
                <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('description', $module->description) }}</textarea>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Cancel</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Save module</button>
            </div>
        </form>
    </div>
@endsection
