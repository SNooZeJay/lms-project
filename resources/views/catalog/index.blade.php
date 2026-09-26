@extends('layouts.app')

@section('title', 'Course catalog')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <header class="border-b border-line pb-8">
            <p class="font-mono text-sm font-semibold text-primary-text">Public catalog</p>
            <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink sm:text-4xl">Browse published courses</h1>
            <p class="mt-3 max-w-2xl leading-7 text-ink-muted">These courses are published and open to every student. Sign in later to enroll.</p>
        </header>

        <form method="GET" action="{{ route('courses.index') }}" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" role="search">
            <div class="lg:col-span-2">
                <label for="q" class="block text-sm font-semibold text-ink">Search by title</label>
                <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Networking basics" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
            </div>

            <div>
                <label for="category" class="block text-sm font-semibold text-ink">Category</label>
                <select id="category" name="category" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <option value="">All categories</option>
                    @foreach ($categories as $availableCategory)
                        <option value="{{ $availableCategory }}" @selected($category === $availableCategory)>{{ $availableCategory }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="level" class="block text-sm font-semibold text-ink">Level</label>
                <select id="level" name="level" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <option value="">All levels</option>
                    @foreach (\App\Enums\CourseLevel::cases() as $availableLevel)
                        <option value="{{ $availableLevel->value }}" @selected($level === $availableLevel->value)>{{ ucfirst($availableLevel->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="course_type" class="block text-sm font-semibold text-ink">Type</label>
                <select id="course_type" name="course_type" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                    <option value="">Free and paid</option>
                    @foreach (\App\Enums\CourseType::cases() as $availableType)
                        <option value="{{ $availableType->value }}" @selected($courseType === $availableType->value)>{{ ucfirst($availableType->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus sm:w-auto sm:flex-1">Apply filters</button>
                <a href="{{ route('courses.index') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus sm:w-auto">Clear filters</a>
            </div>
        </form>

        <p class="mt-6 text-sm text-ink-muted" role="status">
            {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }} published
        </p>

        @if ($courses->isEmpty())
            <section class="mt-6 border-t border-line py-16 text-center" aria-labelledby="empty-catalog-heading">
                <h2 id="empty-catalog-heading" class="text-lg font-semibold text-ink">No published courses</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">No published course matches these filters yet. Try a different search or clear the filters.</p>
                <a href="{{ route('courses.index') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Clear filters</a>
            </section>
        @else
            <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" role="list">
                @foreach ($courses as $course)
                    @php
                        $isFree = $course->course_type === \App\Enums\CourseType::Free;
                    @endphp
                    <li class="flex flex-col border border-line bg-surface p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="text-lg font-semibold text-ink">
                                <a href="{{ route('courses.show', $course) }}" class="rounded-md hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">{{ $course->title }}</a>
                            </h2>
                            <span class="shrink-0 rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold text-ink">{{ $isFree ? 'Free' : 'Paid' }}</span>
                        </div>

                        <p class="mt-2 text-sm text-ink-muted">{{ $course->category ?: 'Uncategorized' }}</p>

                        @if ($course->description)
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-ink-muted">{{ $course->description }}</p>
                        @endif

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-ink-muted">Level</dt>
                                <dd class="mt-1 font-medium text-ink">{{ ucfirst($course->level->value) }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Price</dt>
                                <dd class="mt-1 font-medium text-ink">
                                    @if ($isFree)
                                        Free
                                    @else
                                        PHP {{ number_format(intdiv($course->price_minor, 100)) }}.{{ str_pad($course->price_minor % 100, 2, '0', STR_PAD_LEFT) }}
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Modules</dt>
                                <dd class="mt-1 font-medium text-ink">{{ $course->published_modules_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Lessons</dt>
                                <dd class="mt-1 font-medium text-ink">{{ $course->published_lessons_count }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 text-sm text-ink-muted">By {{ $course->instructor?->name ?? 'IT Learning Hub' }}</p>

                        <a href="{{ route('courses.show', $course) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View course</a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $courses->links() }}</div>
        @endif
    </div>
@endsection
