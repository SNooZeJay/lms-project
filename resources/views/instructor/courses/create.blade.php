@extends('layouts.app')

@section('title', 'Create course')

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('instructor.courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to courses</a>
        <p class="mt-8 font-mono text-sm font-semibold text-primary-text">Instructor workspace</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Create course</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">New Courses start as private drafts. Publication, enrollment, and payment actions are not enabled in this phase.</p>

        <x-form-errors :errors="$errors" />

        <form method="POST" action="{{ route('instructor.courses.store') }}" class="mt-8 space-y-6 border-t border-line pt-8">
            @csrf

            <div>
                <label for="title" class="block text-sm font-semibold text-ink">Course title <span class="text-error-text" aria-hidden="true">*</span></label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="160" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-ink">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="learning_objectives" class="block text-sm font-semibold text-ink">Learning objectives</label>
                <textarea id="learning_objectives" name="learning_objectives" rows="4" maxlength="5000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ old('learning_objectives') }}</textarea>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="category" class="block text-sm font-semibold text-ink">Category</label>
                    <input id="category" name="category" type="text" value="{{ old('category') }}" maxlength="100" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                </div>
                <div>
                    <label for="level" class="block text-sm font-semibold text-ink">Level <span class="text-error-text" aria-hidden="true">*</span></label>
                    <select id="level" name="level" required class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                        @foreach (\App\Enums\CourseLevel::cases() as $level)
                            <option value="{{ $level->value }}" @selected(old('level', $level->value) === $level->value)>{{ ucfirst($level->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="course_type" class="block text-sm font-semibold text-ink">Course type <span class="text-error-text" aria-hidden="true">*</span></label>
                    <select id="course_type" name="course_type" required class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                        @foreach (\App\Enums\CourseType::cases() as $courseType)
                            <option value="{{ $courseType->value }}" @selected(old('course_type', $courseType->value) === $courseType->value)>{{ ucfirst($courseType->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="price_minor" class="block text-sm font-semibold text-ink">Price in centavos <span class="text-error-text" aria-hidden="true">*</span></label>
                    <input id="price_minor" name="price_minor" type="number" min="0" step="1" value="{{ old('price_minor', 0) }}" required class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <p class="mt-2 text-xs leading-5 text-ink-muted">Use centavos: 100 equals PHP 1.00. Free Courses must use 0.</p>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('instructor.courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Cancel</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Create private draft</button>
            </div>
        </form>
    </div>
@endsection
