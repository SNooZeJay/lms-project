@extends('layouts.app-shell')

@section('title', $lesson->title)
@section('workspace-context', $course->title)

@section('content')
@section('measure', 'narrow')
            @if (session('status'))
            <x-note tone="success" class="mb-6">{{ session('status') }}</x-note>
        @endif

        <article>
            <header class="border-b border-line pb-8">
                <p class="eyebrow">
                    {{ $course->title }}
                    <span aria-hidden="true">·</span>
                    {{ $lesson->module->title }}
                </p>

                <h1 class="mt-3 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                    {{ $lesson->title }}
                </h1>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <x-badge tone="neutral">Lesson {{ $lesson->position }}</x-badge>
                    <x-badge tone="neutral">{{ $lesson->is_required ? 'Required' : 'Optional' }}</x-badge>
                    @if ($lesson->estimated_minutes)
                        <x-badge tone="neutral">
                            <x-icon name="clock" size="xs" />
                            {{ $lesson->estimated_minutes }} min read
                        </x-badge>
                    @endif
                </div>

                @if ($lesson->summary)
                    <p class="prose-measure mt-5 text-lg leading-8 text-ink-muted">{{ $lesson->summary }}</p>
                @endif
            </header>

            {{-- The lesson body. Blank lines become separate paragraphs, and the
                 measure is capped so a line stays comfortable to read. --}}
            <section class="mt-10" aria-labelledby="lesson-content-heading">
                <h2 id="lesson-content-heading" class="sr-only">Lesson content</h2>

                @if ($lesson->content_text)
                    <div class="prose-measure space-y-5 leading-8 text-ink">
                        @foreach (preg_split('/\R{2,}/', $lesson->content_text) as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p>{{ trim($paragraph) }}</p>
                            @endif
                        @endforeach
                    </div>
                @else
                    <x-note tone="neutral">This lesson has no written content yet.</x-note>
                @endif
            </section>

            <section class="mt-12" aria-labelledby="lesson-materials-heading">
                <h2 id="lesson-materials-heading" class="text-xl font-semibold text-ink">Learning materials</h2>

                @if ($lesson->learningMaterials->isEmpty())
                    <p class="mt-2 text-sm leading-6 text-ink-muted">
                        No learning materials are attached to this lesson yet.
                    </p>
                @else
                    <ul role="list" class="mt-5 space-y-4">
                        @foreach ($lesson->learningMaterials as $material)
                            <li class="card p-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="font-semibold text-ink">{{ $material->title }}</h3>
                                    <x-badge tone="neutral">
                                        {{ \App\Support\StatusLabel::words($material->material_type->value) }}
                                    </x-badge>
                                </div>

                                @if ($material->content_text)
                                    <pre class="mt-4 overflow-x-auto rounded-md border border-line bg-surface-muted px-4 py-3 font-mono text-sm leading-6 whitespace-pre-wrap text-ink">{{ $material->content_text }}</pre>
                                @endif

                                @if ($material->external_url)
                                    <p class="mt-4">
                                        <a
                                            href="{{ $material->external_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-11 max-w-full items-center gap-2 break-all text-sm font-semibold text-primary-text underline underline-offset-4 transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                        >
                                            <x-icon name="external" size="sm" class="shrink-0" />
                                            <span>{{ $material->external_url }}</span>
                                            <span class="sr-only">(opens in a new tab)</span>
                                        </a>
                                    </p>
                                @endif

                                @if ($material->storage_path)
                                    <div class="mt-4">
                                        <x-btn
                                            :href="route('student.materials.download', [$course, $lesson, $material])"
                                            variant="secondary"
                                            size="md"
                                        >
                                            <x-icon name="download" size="sm" />
                                            Download {{ $material->title }}
                                        </x-btn>
                                        <p class="mt-2 text-sm leading-6 text-ink-muted">
                                            Stored privately. This link works only while you are enrolled.
                                        </p>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Progress. The only control on the page, placed at the end where
                 a reader finishes the content. --}}
            <section class="mt-12" aria-labelledby="lesson-progress-heading">
                <h2 id="lesson-progress-heading" class="text-xl font-semibold text-ink">Your progress</h2>

                <div class="card mt-4 p-5">
                    @if ($progress->isCompleted())
                        <x-note tone="success">
                            <span class="font-semibold">Completed.</span>
                            @if ($progress->completed_at)
                                You finished this lesson on {{ $progress->completed_at->format('M j, Y') }}.
                            @else
                                This lesson is recorded as complete.
                            @endif
                        </x-note>
                    @else
                        <p class="text-sm leading-6 text-ink-muted">
                            Marking this lesson complete updates your course progress. It is recorded on the server
                            and cannot be changed from the browser.
                        </p>

                        <form method="POST" action="{{ route('student.lessons.complete', [$course, $lesson]) }}" class="mt-4" data-pending>
                            @csrf
                            <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                                <x-icon name="check" size="sm" />
                                <span data-pending-text>Mark as complete</span>
                            </x-btn>
                        </form>
                    @endif
                </div>
            </section>
        </article>

        <div class="mt-10 border-t border-line pt-6">
            <x-btn :href="route('student.courses.show', $course)" variant="secondary" size="md">
                <x-icon name="arrow-left" size="sm" />
                Back to {{ $course->title }}
            </x-btn>
        </div>
@endsection
