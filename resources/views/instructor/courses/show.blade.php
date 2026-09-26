@extends('layouts.app')

@section('title', $course->title)

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('instructor.courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to courses</a>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

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
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">You can add and edit draft Modules and Lessons here. Reordering, uploads, deletion, and public publishing are not enabled yet.</p>
                <a href="{{ route('instructor.courses.edit', $course) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Edit course details</a>
            </div>
        </header>

        @forelse ($course->modules as $module)
            <section class="mt-8 border border-line bg-surface shadow-sm" aria-labelledby="module-{{ $module->id }}">
                <div class="flex flex-col gap-3 border-b border-line bg-surface-muted px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-text">Module {{ $module->position }}</p>
                        <h2 id="module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">{{ $module->title }}</h2>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-medium text-ink-muted">{{ ucfirst($module->status->value) }}</span>
                        <a href="{{ route('instructor.courses.modules.edit', [$course, $module]) }}" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Edit module</a>
                    </div>
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
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-muted">
                                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ ucfirst($lesson->status->value) }}</span>
                                <span class="rounded-full bg-surface-muted px-2.5 py-1">{{ $lesson->is_required ? 'Required' : 'Optional' }}</span>
                                <a href="{{ route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson]) }}" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Edit lesson</a>
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

                @php
                    $lessonFailed = old('form_context') === 'lesson:'.$module->id;
                @endphp
                <details class="border-t border-line bg-surface-muted px-5 py-4"{!! $lessonFailed ? ' open' : '' !!}>
                    <summary class="cursor-pointer text-sm font-semibold text-primary-text">Add lesson</summary>
                    <form method="POST" action="{{ route('instructor.courses.modules.lessons.store', [$course, $module]) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="form_context" value="lesson:{{ $module->id }}">
                        <div>
                            <label for="lesson-title-{{ $module->id }}" class="block text-sm font-semibold text-ink">Lesson title</label>
                            <input id="lesson-title-{{ $module->id }}" name="title" type="text" required maxlength="160" value="{{ $lessonFailed ? old('title') : '' }}" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                        </div>
                        <div>
                            <label for="lesson-summary-{{ $module->id }}" class="block text-sm font-semibold text-ink">Summary</label>
                            <textarea id="lesson-summary-{{ $module->id }}" name="summary" rows="3" maxlength="5000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ $lessonFailed ? old('summary') : '' }}</textarea>
                        </div>
                        <div>
                            <label for="lesson-content-{{ $module->id }}" class="block text-sm font-semibold text-ink">Lesson content</label>
                            <textarea id="lesson-content-{{ $module->id }}" name="content_text" rows="4" maxlength="100000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ $lessonFailed ? old('content_text') : '' }}</textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="lesson-minutes-{{ $module->id }}" class="block text-sm font-semibold text-ink">Estimated minutes</label>
                                <input id="lesson-minutes-{{ $module->id }}" name="estimated_minutes" type="number" min="1" step="1" value="{{ $lessonFailed ? old('estimated_minutes') : '' }}" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                            </div>
                            <label class="flex min-h-11 items-center gap-3 self-end rounded-md border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink">
                                <input type="hidden" name="is_required" value="0">
                                <input name="is_required" value="1" type="checkbox" @checked($lessonFailed ? (bool) old('is_required', true) : true) class="h-4 w-4 rounded border-line text-primary focus:ring-focus">
                                Required lesson
                            </label>
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Add private lesson</button>
                    </form>
                </details>
            </section>
        @empty
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="empty-outline-heading">
                <h2 id="empty-outline-heading" class="text-lg font-semibold text-ink">No Modules yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">This Course is a private draft. Add the first Module to begin its outline.</p>
            </section>
        @endforelse

        @php
            $moduleFailed = old('form_context') === 'module';
        @endphp
        <section class="mt-8 border-t border-line pt-8" aria-labelledby="add-module-heading">
            <h2 id="add-module-heading" class="text-lg font-semibold text-ink">Add module</h2>
            <p class="mt-2 text-sm leading-6 text-ink-muted">New Modules are private drafts. Their order is assigned by the server.</p>
            <form method="POST" action="{{ route('instructor.courses.modules.store', $course) }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="form_context" value="module">
                <div>
                    <label for="module-title" class="block text-sm font-semibold text-ink">Module title</label>
                    <input id="module-title" name="title" type="text" required maxlength="160" value="{{ $moduleFailed ? old('title') : '' }}" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                </div>
                <div>
                    <label for="module-description" class="block text-sm font-semibold text-ink">Module description</label>
                    <textarea id="module-description" name="description" rows="3" maxlength="5000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ $moduleFailed ? old('description') : '' }}</textarea>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Add private module</button>
            </form>
        </section>
    </div>
@endsection
