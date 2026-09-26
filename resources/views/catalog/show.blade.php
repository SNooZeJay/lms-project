@extends('layouts.app')

@section('title', $course->title)

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to catalog</a>

        <header class="mt-8 border-b border-line pb-8">
            <p class="font-mono text-sm font-semibold text-primary-text">Published course</p>
            <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink sm:text-4xl">{{ $course->title }}</h1>

            <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-muted">
                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ ucfirst($course->level->value) }}</span>
                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $course->course_type === \App\Enums\CourseType::Free ? 'Free' : 'Paid' }}</span>
                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $course->category ?: 'Uncategorized' }}</span>
            </div>

            @if ($course->description)
                <p class="mt-5 max-w-3xl text-lg leading-8 text-ink-muted">{{ $course->description }}</p>
            @endif

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-ink-muted">Price</dt>
                    <dd class="mt-1 font-semibold text-ink">
                        @if ($course->course_type === \App\Enums\CourseType::Free)
                            Free
                        @else
                            PHP {{ number_format(intdiv($course->price_minor, 100)) }}.{{ str_pad($course->price_minor % 100, 2, '0', STR_PAD_LEFT) }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Instructor</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $course->instructor?->name ?? 'IT Learning Hub' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Modules</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $course->modules->count() }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">Published</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $course->published_at?->format('M j, Y') ?? 'Recently' }}</dd>
                </div>
            </dl>

            <p class="mt-6 border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">Enrollment is not open yet. Lesson content and materials unlock after you enroll in a later release.</p>
        </header>

        @if ($course->learning_objectives)
            <section class="mt-10" aria-labelledby="objectives-heading">
                <h2 id="objectives-heading" class="text-xl font-semibold text-ink">What you will learn</h2>
                <p class="mt-3 max-w-3xl whitespace-pre-line leading-7 text-ink-muted">{{ $course->learning_objectives }}</p>
            </section>
        @endif

        <section class="mt-12" aria-labelledby="outline-heading">
            <h2 id="outline-heading" class="text-xl font-semibold text-ink">Course outline</h2>
            <p class="mt-2 text-sm leading-6 text-ink-muted">Module and Lesson titles only. Lesson content stays private.</p>

            @forelse ($course->modules as $module)
                <section class="mt-6 border border-line bg-surface shadow-sm" aria-labelledby="catalog-module-{{ $module->id }}">
                    <div class="border-b border-line bg-surface-muted px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-text">Module {{ $module->position }}</p>
                        <h3 id="catalog-module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">{{ $module->title }}</h3>
                    </div>

                    @if ($module->lessons->isEmpty())
                        <p class="px-5 py-5 text-sm text-ink-muted">No published Lessons in this Module yet.</p>
                    @else
                        <ol class="divide-y divide-line" role="list">
                            @foreach ($module->lessons as $lesson)
                                <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Lesson {{ $lesson->position }}</p>
                                        <p class="mt-1 font-semibold text-ink">{{ $lesson->title }}</p>
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
                <p class="mt-6 border-t border-line py-10 text-sm text-ink-muted">This course has no published outline yet.</p>
            @endforelse
        </section>
    </div>
@endsection
