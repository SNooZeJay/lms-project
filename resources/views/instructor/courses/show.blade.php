@extends('layouts.app')

@section('title', $course->title)

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('instructor.courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to courses</a>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <header class="mt-8 border-b border-line pb-8">
            <p class="font-mono text-sm font-semibold text-primary-text">Course outline</p>
            <div class="mt-3 flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-3xl font-[650] tracking-tight text-ink">{{ $course->title }}</h1>
                    @if ($course->description)
                        <p class="mt-3 max-w-3xl leading-7 text-ink-muted">{{ $course->description }}</p>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4 lg:min-w-96">
                    <div><dt class="text-ink-muted">Status</dt><dd class="mt-1 font-semibold text-ink">{{ ucfirst($course->status->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Type</dt><dd class="mt-1 font-semibold text-ink">{{ ucfirst($course->course_type->value) }}</dd></div>
                    <div><dt class="text-ink-muted">Level</dt><dd class="mt-1 font-semibold text-ink">{{ ucfirst($course->level->value) }}</dd></div>
                    <div>
                        <dt class="text-ink-muted">Price</dt>
                        <dd class="mt-1 font-semibold text-ink">PHP {{ number_format(intdiv($course->price_minor, 100)) }}.{{ str_pad($course->price_minor % 100, 2, '0', STR_PAD_LEFT) }}</dd>
                    </div>
                </dl>
            </div>
            <p class="mt-6 border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">This Phase 5A outline is read-only. Module, Lesson, and material authoring controls will be added in a later approved slice.</p>
        </header>

        @forelse ($course->modules as $module)
            <section class="mt-8 border border-line bg-surface shadow-sm" aria-labelledby="module-{{ $module->id }}">
                <div class="flex flex-col gap-3 border-b border-line bg-surface-muted px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-text">Module {{ $module->position }}</p>
                        <h2 id="module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">{{ $module->title }}</h2>
                    </div>
                    <span class="text-sm font-medium text-ink-muted">{{ ucfirst($module->status->value) }}</span>
                </div>

                @forelse ($module->lessons as $lesson)
                    <article class="border-b border-line px-5 py-5 last:border-b-0" aria-labelledby="lesson-{{ $lesson->id }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Lesson {{ $lesson->position }}</p>
                                <h3 id="lesson-{{ $lesson->id }}" class="mt-1 font-semibold text-ink">{{ $lesson->title }}</h3>
                                @if ($lesson->summary)
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-muted">{{ $lesson->summary }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold text-ink-muted">
                                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ ucfirst($lesson->status->value) }}</span>
                                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $lesson->is_required ? 'Required' : 'Optional' }}</span>
                            </div>
                        </div>

                        @if ($lesson->learningMaterials->isNotEmpty())
                            <ul class="mt-4 grid gap-2 sm:grid-cols-2" role="list">
                                @foreach ($lesson->learningMaterials as $material)
                                    <li class="border-l-2 border-line px-3 py-2 text-sm">
                                        <span class="font-medium text-ink">{{ $material->title }}</span>
                                        <span class="block text-xs text-ink-muted">{{ ucfirst($material->material_type->value) }} · Position {{ $material->position }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-sm text-ink-muted">No Learning Materials are recorded yet.</p>
                        @endif
                    </article>
                @empty
                    <p class="px-5 py-5 text-sm text-ink-muted">No Lessons are recorded in this Module yet.</p>
                @endforelse
            </section>
        @empty
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="empty-outline-heading">
                <h2 id="empty-outline-heading" class="text-lg font-semibold text-ink">No Modules yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">This Course is a private draft. Module authoring will be added in the next approved UI slice.</p>
            </section>
        @endforelse
    </div>
@endsection
