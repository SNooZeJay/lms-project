@extends('layouts.app')

@section('title', 'Course catalog')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Public catalog"
            title="Browse published courses"
            description="These courses are published and open to everyone. Sign in to enroll in one."
        />

        {{-- Search and filters. Every control has a visible label, and a way back
             to the full list is always offered. --}}
        <form
            method="GET"
            action="{{ route('courses.index') }}"
            role="search"
            class="card mt-8 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div class="lg:col-span-2">
                <label for="q" class="field-label">Search by course title</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Networking basics"
                    class="field-control field-control-surface"
                >
            </div>

            <div>
                <label for="category" class="field-label">Category</label>
                <select id="category" name="category" class="field-control field-control-surface">
                    <option value="">All categories</option>
                    @foreach ($categories as $availableCategory)
                        <option value="{{ $availableCategory }}" @selected($category === $availableCategory)>
                            {{ $availableCategory }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="level" class="field-label">Level</label>
                <select id="level" name="level" class="field-control field-control-surface">
                    <option value="">All levels</option>
                    @foreach (\App\Enums\CourseLevel::cases() as $availableLevel)
                        <option value="{{ $availableLevel->value }}" @selected($level === $availableLevel->value)>
                            {{ \App\Support\StatusLabel::words($availableLevel->value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="course_type" class="field-label">Price type</label>
                <select id="course_type" name="course_type" class="field-control field-control-surface">
                    <option value="">Free and paid</option>
                    @foreach (\App\Enums\CourseType::cases() as $availableType)
                        <option value="{{ $availableType->value }}" @selected($courseType === $availableType->value)>
                            {{ \App\Support\StatusLabel::words($availableType->value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-btn type="submit" variant="primary" size="md" class="sm:flex-1">
                    <x-icon name="search" size="sm" />
                    Apply filters
                </x-btn>
                <x-btn :href="route('courses.index')" variant="secondary" size="md">
                    Clear filters
                </x-btn>
            </div>
        </form>

        <p class="mt-6 text-sm text-ink-muted" role="status">
            {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }} published
        </p>

        @if ($courses->isEmpty())
            <div class="card mt-4">
                <x-empty-state
                    icon="search"
                    title="No published courses match"
                    description="No published course matches these filters yet. Try a different search, or clear the filters to see everything."
                >
                    <x-btn :href="route('courses.index')" variant="primary" size="md">
                        Clear filters
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            <ul role="list" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <li class="card flex flex-col p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="text-lg font-semibold text-balance text-ink">
                                <a
                                    href="{{ route('courses.show', $course) }}"
                                    class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                >{{ $course->title }}</a>
                            </h2>
                            <x-badge :tone="$course->course_type->value === 'free' ? 'success' : 'primary'" class="shrink-0">
                                {{ $course->course_type->value === 'free' ? 'Free' : 'Paid' }}
                            </x-badge>
                        </div>

                        <p class="mt-2 text-sm text-ink-muted">{{ $course->category ?: 'Uncategorized' }}</p>

                        @if ($course->description)
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-ink-muted">{{ $course->description }}</p>
                        @endif

                        {{-- A free course shows the word Free, never a peso amount. --}}
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="meta-label">Level</dt>
                                <dd class="mt-1 font-medium text-ink">
                                    {{ \App\Support\StatusLabel::words($course->level->value) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="meta-label">Price</dt>
                                <dd class="mt-1 font-semibold text-ink">
                                    <x-amount
                                        :minor="$course->price_minor"
                                        :type="$course->course_type"
                                        :currency="$course->currency"
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt class="meta-label">Modules</dt>
                                <dd class="mt-1 font-medium text-ink tabular-nums">{{ $course->published_modules_count }}</dd>
                            </div>
                            <div>
                                <dt class="meta-label">Lessons</dt>
                                <dd class="mt-1 font-medium text-ink tabular-nums">{{ $course->published_lessons_count }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 text-sm text-ink-muted">
                            By {{ $course->instructor?->name ?? 'IT Learning Hub' }}
                        </p>

                        <x-btn :href="route('courses.show', $course)" variant="secondary" size="md" class="mt-5">
                            View course
                            <x-icon name="arrow-right" size="sm" />
                        </x-btn>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $courses->links() }}</div>
        @endif
    </div>
@endsection
