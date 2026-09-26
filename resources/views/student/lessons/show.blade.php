@extends('layouts.app')

@section('title', $lesson->title)

@section('content')
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.courses.show', $course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to {{ $course->title }}</a>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <header class="mt-8 border-b border-line pb-8">
            <p class="font-mono text-sm font-semibold text-primary-text">{{ $course->title }} · {{ $lesson->module->title }}</p>
            <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">{{ $lesson->title }}</h1>

            <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-muted">
                <span class="rounded-full bg-surface-muted px-2.5 py-1">Lesson {{ $lesson->position }}</span>
                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $lesson->is_required ? 'Required' : 'Optional' }}</span>
                @if ($lesson->estimated_minutes)
                    <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $lesson->estimated_minutes }} min</span>
                @endif
            </div>

            @if ($lesson->summary)
                <p class="mt-5 text-lg leading-8 text-ink-muted">{{ $lesson->summary }}</p>
            @endif
        </header>

        <section class="mt-10" aria-labelledby="lesson-content-heading">
            <h2 id="lesson-content-heading" class="sr-only">Lesson content</h2>

            @if ($lesson->content_text)
                <div class="space-y-4 leading-8 text-ink">
                    @foreach (preg_split('/\R{2,}/', $lesson->content_text) as $paragraph)
                        @if (trim($paragraph) !== '')
                            <p>{{ $paragraph }}</p>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="text-sm text-ink-muted">This lesson has no written content yet.</p>
            @endif
        </section>

        <section class="mt-12" aria-labelledby="lesson-materials-heading">
            <h2 id="lesson-materials-heading" class="text-xl font-semibold text-ink">Learning materials</h2>

            @if ($lesson->learningMaterials->isEmpty())
                <p class="mt-3 text-sm text-ink-muted">No learning materials are attached to this lesson yet.</p>
            @else
                <ul class="mt-5 space-y-4" role="list">
                    @foreach ($lesson->learningMaterials as $material)
                        <li class="border-l-2 border-line pl-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-ink">{{ $material->title }}</h3>
                                <span class="rounded-full bg-surface-muted px-2.5 py-0.5 text-xs font-semibold text-ink-muted">{{ ucfirst(str_replace('_', ' ', $material->material_type->value)) }}</span>
                            </div>

                            @if ($material->content_text)
                                <pre class="mt-3 overflow-x-auto whitespace-pre-wrap rounded-md bg-surface-muted px-4 py-3 font-mono text-sm leading-6 text-ink">{{ $material->content_text }}</pre>
                            @endif

                            @if ($material->external_url)
                                <p class="mt-3">
                                    <a href="{{ $material->external_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center break-all text-sm font-semibold text-primary-text underline underline-offset-4 hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                                        {{ $material->external_url }}
                                        <span class="sr-only">(opens in a new tab)</span>
                                    </a>
                                </p>
                            @endif

                            @if ($material->storage_path)
                                <p class="mt-3 text-sm text-ink-muted">A file is attached to this material. File downloads are not available yet.</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-12" aria-labelledby="lesson-progress-heading">
            <h2 id="lesson-progress-heading" class="text-xl font-semibold text-ink">Your progress</h2>

            @if ($progress->isCompleted())
                <p class="mt-4 inline-flex items-center gap-2 rounded-md border border-success-text bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">
                    <span aria-hidden="true">✓</span>
                    Completed
                    @if ($progress->completed_at)
                        <span class="font-normal">on {{ $progress->completed_at->format('M j, Y') }}</span>
                    @endif
                </p>
            @else
                <form method="POST" action="{{ route('student.lessons.complete', [$course, $lesson]) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Mark as complete</button>
                </form>
            @endif
        </section>
    </div>
@endsection
