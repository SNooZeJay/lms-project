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
                <p class="border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">
                    You can add and edit Modules and Lessons here.
                    @if ($course->published_at)
                        First published {{ $course->published_at->format('M j, Y g:i A') }}.
                    @endif
                    Reordering, uploads, and deletion are not enabled yet. Archiving keeps enrollments and progress.
                </p>
                <div class="flex shrink-0 flex-col gap-3 sm:flex-row">
                    <a href="{{ route('instructor.courses.edit', $course) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Edit course details</a>

                    @if ($course->status === \App\Enums\CourseStatus::Archived)
                        <form method="POST" action="{{ route('instructor.courses.restore', $course) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Restore course</button>
                        </form>
                    @else
                        @if ($course->status === \App\Enums\CourseStatus::Draft)
                            <form method="POST" action="{{ route('instructor.courses.publish', $course) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Publish course</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('instructor.courses.unpublish', $course) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Unpublish course</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('instructor.courses.archive', $course) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Archive course</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($course->status === \App\Enums\CourseStatus::Draft)
                <p class="mt-4 border-l-4 border-line bg-surface-muted px-4 py-3 text-sm leading-6 text-ink-muted">Publishing needs at least one Module and one Lesson. Students cannot see a draft course.</p>
            @endif
        </header>

        @forelse ($course->modules as $module)
            <section class="mt-8 border border-line bg-surface shadow-sm" aria-labelledby="module-{{ $module->id }}">
                <div class="flex flex-col gap-3 border-b border-line bg-surface-muted px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-text">Module {{ $module->position }}</p>
                        <h2 id="module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">{{ $module->title }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-medium text-ink-muted">{{ ucfirst($module->status->value) }}</span>
                        <a href="{{ route('instructor.courses.modules.edit', [$course, $module]) }}" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Edit module</a>
                        @if ($module->status === \App\Enums\ContentStatus::Archived)
                            <form method="POST" action="{{ route('instructor.courses.modules.restore', [$course, $module]) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Restore module</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('instructor.courses.modules.archive', [$course, $module]) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Archive module</button>
                            </form>
                        @endif
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
                                @if ($lesson->status === \App\Enums\ContentStatus::Archived)
                                    <form method="POST" action="{{ route('instructor.courses.modules.lessons.restore', [$course, $module, $lesson]) }}" class="inline-flex">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Restore lesson</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('instructor.courses.modules.lessons.archive', [$course, $module, $lesson]) }}" class="inline-flex">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Archive lesson</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($lesson->learningMaterials->isNotEmpty())
                            <ul class="mt-4 grid gap-2 sm:grid-cols-2" role="list">
                                @foreach ($lesson->learningMaterials as $material)
                                    <li class="border-l-2 border-line px-3 py-2 text-sm">
                                        <span class="font-medium text-ink">{{ $material->title }}</span>
                                        <span class="block text-xs text-ink-muted">Material {{ $material->position }} · {{ ucfirst(str_replace('_', ' ', $material->material_type->value)) }}</span>
                                        <a href="{{ route('instructor.courses.materials.edit', [$course, $module, $lesson, $material]) }}" class="mt-2 inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Edit material</a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-sm text-ink-muted">No Learning Materials are recorded yet.</p>
                        @endif

                        @php
                            $materialFailed = old('form_context') === 'material:'.$lesson->id;
                        @endphp
                        <details class="mt-4 border border-line bg-surface-muted px-4 py-3"{!! $materialFailed ? ' open' : '' !!}>
                            <summary class="cursor-pointer text-sm font-semibold text-primary-text">Add material</summary>
                            <form method="POST" action="{{ route('instructor.courses.materials.store', [$course, $module, $lesson]) }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="form_context" value="material:{{ $lesson->id }}">
                                <div>
                                    <label for="material-title-{{ $lesson->id }}" class="block text-sm font-semibold text-ink">Material title</label>
                                    <input id="material-title-{{ $lesson->id }}" name="title" type="text" required maxlength="255" value="{{ $materialFailed ? old('title') : '' }}" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                                </div>
                                <div>
                                    <label for="material-type-{{ $lesson->id }}" class="block text-sm font-semibold text-ink">Material type</label>
                                    <select id="material-type-{{ $lesson->id }}" name="material_type" required class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                                        @foreach (\App\Enums\LearningMaterialType::cases() as $type)
                                            <option value="{{ $type->value }}" @selected($materialFailed && old('material_type') === $type->value)>{{ ucfirst(str_replace('_', ' ', $type->value)) }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs leading-5 text-ink-muted">Image, PDF, and Document materials need a file. Files are stored privately and served only to authorized readers.</p>
                                </div>
                                <div>
                                    <label for="material-file-{{ $lesson->id }}" class="block text-sm font-semibold text-ink">Material file</label>
                                    <input id="material-file-{{ $lesson->id }}" name="file" type="file" class="mt-2 block w-full min-h-11 rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink file:mr-3 file:min-h-11 file:rounded-md file:border-0 file:bg-surface-muted file:px-3 file:text-sm file:font-semibold file:text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                                    <p class="mt-2 text-xs leading-5 text-ink-muted">10 MB maximum. Executables and scripts are rejected.</p>
                                </div>
                                <div>
                                    <label for="material-content-{{ $lesson->id }}" class="block text-sm font-semibold text-ink">Material content</label>
                                    <textarea id="material-content-{{ $lesson->id }}" name="content_text" rows="4" maxlength="100000" class="mt-2 w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">{{ $materialFailed ? old('content_text') : '' }}</textarea>
                                    <p class="mt-2 text-xs leading-5 text-ink-muted">Required for Text and Code materials.</p>
                                </div>
                                <div>
                                    <label for="material-link-{{ $lesson->id }}" class="block text-sm font-semibold text-ink">Material link</label>
                                    <input id="material-link-{{ $lesson->id }}" name="external_url" type="url" inputmode="url" maxlength="2048" placeholder="https://example.com/page" value="{{ $materialFailed ? old('external_url') : '' }}" class="mt-2 min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                                    <p class="mt-2 text-xs leading-5 text-ink-muted">Required for Video link and External link materials.</p>
                                </div>
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Add material</button>
                            </form>
                        </details>
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

                @php
                    $reorderableLessons = $module->lessons->where('status.value', '!=', \App\Enums\ContentStatus::Archived->value)->values();
                @endphp

                @if ($reorderableLessons->count() > 1)
                    <details class="border-t border-line px-5 py-4">
                        <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Reorder lessons</summary>

                        <form method="POST" action="{{ route('instructor.courses.modules.lessons.reorder', [$course, $module]) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('PATCH')

                            @foreach ($reorderableLessons as $index => $reorderable)
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label for="lesson-position-{{ $reorderable->id }}" class="text-sm font-medium text-ink">
                                        <span class="font-mono text-xs text-ink-muted">now {{ $reorderable->position }}</span>
                                        <span class="block">{{ $reorderable->title }}</span>
                                    </label>
                                    <input id="lesson-position-{{ $reorderable->id }}" name="positions[{{ $reorderable->id }}]" type="number" min="1" max="{{ $reorderableLessons->count() }}" step="1" required value="{{ $index + 1 }}" class="min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink sm:w-24 focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                                </div>
                            @endforeach

                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Save lesson order</button>
                        </form>
                    </details>
                @endif
            </section>
        @empty
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="empty-outline-heading">
                <h2 id="empty-outline-heading" class="text-lg font-semibold text-ink">No Modules yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">This Course is a private draft. Add the first Module to begin its outline.</p>
            </section>
        @endforelse

        @php
            $reorderableModules = $course->modules->where('status.value', '!=', \App\Enums\ContentStatus::Archived->value)->values();
        @endphp

        @if ($reorderableModules->count() > 1)
            <section class="mt-8 border border-line bg-surface-muted p-5" aria-labelledby="reorder-modules-heading">
                <h2 id="reorder-modules-heading" class="text-lg font-semibold text-ink">Reorder modules</h2>
                <p class="mt-1 text-sm leading-6 text-ink-muted">Enter a position from 1 to {{ $reorderableModules->count() }} for each module. The saved order is the outline order students read.</p>

                <x-form-errors :errors="$errors" />

                <form method="POST" action="{{ route('instructor.courses.modules.reorder', $course) }}" class="mt-4">
                    @csrf
                    @method('PATCH')

                    <ul class="space-y-3" role="list">
                        @foreach ($reorderableModules as $index => $reorderable)
                            <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <label for="module-position-{{ $reorderable->id }}" class="text-sm font-medium text-ink">
                                    <span class="font-mono text-xs text-ink-muted">now {{ $reorderable->position }}</span>
                                    <span class="block">{{ $reorderable->title }}</span>
                                </label>
                                <input id="module-position-{{ $reorderable->id }}" name="positions[{{ $reorderable->id }}]" type="number" min="1" max="{{ $reorderableModules->count() }}" step="1" required value="{{ $index + 1 }}" class="min-h-11 w-full rounded-md border border-line bg-surface px-3 py-2 text-ink sm:w-24 focus:border-primary focus:outline-none focus:ring-3 focus:ring-focus">
                            </li>
                        @endforeach
                    </ul>

                    <button type="submit" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Save module order</button>
                </form>
            </section>
        @endif

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
