@extends('layouts.app')

@section('title', $course->title)

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to my courses</a>

        <header class="mt-8 border-b border-line pb-8">
            <p class="font-mono text-sm font-semibold text-primary-text">Enrolled course</p>
            <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">{{ $course->title }}</h1>

            @if ($course->description)
                <p class="mt-4 max-w-3xl text-lg leading-8 text-ink-muted">{{ $course->description }}</p>
            @endif

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-ink-muted">Instructor</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $course->instructor?->name ?? 'IT Learning Hub' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Level</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ ucfirst($course->level->value) }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Modules</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $course->modules->count() }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Lessons</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $course->modules->sum(fn ($module) => $module->lessons->count()) }}</dd>
                </div>
            </dl>

            @if ($course->status !== \App\Enums\CourseStatus::Published)
                <p class="mt-6 border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">This course is not published right now. Your enrollment is kept, and published lessons stay available.</p>
            @endif
        </header>

        <section class="mt-10" aria-labelledby="student-outline-heading">
            <h2 id="student-outline-heading" class="text-xl font-semibold text-ink">Your lessons</h2>
            <p class="mt-2 text-sm leading-6 text-ink-muted">Open any lesson to read its content and materials.</p>

            @forelse ($course->modules as $module)
                <section class="mt-6 border border-line bg-surface shadow-sm" aria-labelledby="student-module-{{ $module->id }}">
                    <div class="border-b border-line bg-surface-muted px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-text">Module {{ $module->position }}</p>
                        <h3 id="student-module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">{{ $module->title }}</h3>
                    </div>

                    @if ($module->lessons->isEmpty())
                        <p class="px-5 py-5 text-sm text-ink-muted">No published lessons in this module yet.</p>
                    @else
                        <ol class="divide-y divide-line" role="list">
                            @foreach ($module->lessons as $lesson)
                                <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Lesson {{ $lesson->position }}</p>
                                        <p class="mt-1 font-semibold text-ink">
                                            <a href="{{ route('student.lessons.show', [$course, $lesson]) }}" class="rounded-md hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">{{ $lesson->title }}</a>
                                        </p>
                                        @if ($lesson->summary)
                                            <p class="mt-1 max-w-2xl text-sm leading-6 text-ink-muted">{{ $lesson->summary }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-muted">
                                        <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $lesson->is_required ? 'Required' : 'Optional' }}</span>
                                        @if ($lesson->estimated_minutes)
                                            <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $lesson->estimated_minutes }} min</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @empty
                <p class="mt-6 border-t border-line py-10 text-sm text-ink-muted">This course has no published lessons yet.</p>
            @endforelse
        </section>

        <p class="mt-10 border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">Progress tracking is not built yet, so nothing is marked complete.</p>
    </div>
@endsection
