@extends('layouts.app-shell')

@section('title', $course->title)
@section('workspace-context', 'Teaching workspace')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        @if (session('status'))
            <x-note tone="success" class="mb-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mb-6" />

        <header class="border-b border-line pb-8">
            <p class="eyebrow">Course outline</p>

            <div class="mt-2 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <h1 class="text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                        {{ $course->title }}
                    </h1>
                    @if ($course->description)
                        <p class="prose-measure mt-3 leading-7 text-ink-muted">{{ $course->description }}</p>
                    @endif
                </div>

                <dl class="grid shrink-0 grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2 lg:grid-cols-4 lg:min-w-96">
                    <div>
                        <dt class="meta-label">Status</dt>
                        <dd class="mt-1"><x-status :value="$course->status->value" /></dd>
                    </div>
                    <div>
                        <dt class="meta-label">Type</dt>
                        <dd class="meta-value">{{ \App\Support\StatusLabel::words($course->course_type->value) }}</dd>
                    </div>
                    <div>
                        <dt class="meta-label">Level</dt>
                        <dd class="meta-value">{{ \App\Support\StatusLabel::words($course->level->value) }}</dd>
                    </div>
                    <div>
                        <dt class="meta-label">Price</dt>
                        <dd class="mt-1 font-semibold text-ink">
                            <x-amount :minor="$course->price_minor" :type="$course->course_type" :currency="$course->currency" />
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="mt-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <x-note tone="neutral" class="lg:max-w-xl">
                    You can add and edit Modules and Lessons here.
                    @if ($course->published_at)
                        First published {{ $course->published_at->format('M j, Y g:i A') }}.
                    @endif
                    Reordering, uploads, and deletion are not enabled yet. Archiving keeps enrollments and progress.
                </x-note>

                <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap lg:justify-end">
                    <x-btn :href="route('instructor.courses.edit', $course)" variant="secondary" size="md">
                        <x-icon name="pencil" size="sm" />
                        Edit course details
                    </x-btn>

                    {{--
                        The learner roster lives on the Course page, because a
                        Course is where a teacher looks for it. The dashboard can
                        only carry the handful of learners who are furthest
                        behind, which answers "who needs chasing" and not "how is
                        this cohort doing".
                    --}}
                    <x-btn :href="route('instructor.courses.students', $course)" variant="secondary" size="md">
                        <x-icon name="users" size="sm" />
                        View learners
                    </x-btn>

                    @if ($course->status === \App\Enums\CourseStatus::Archived)
                        <form method="POST" action="{{ route('instructor.courses.restore', $course) }}" data-pending>
                            @csrf
                            <x-btn type="submit" variant="primary" size="md" data-pending-button>
                                <span data-pending-text>Restore course</span>
                            </x-btn>
                        </form>
                    @else
                        @if ($course->status === \App\Enums\CourseStatus::Draft)
                            <form method="POST" action="{{ route('instructor.courses.publish', $course) }}" data-pending>
                                @csrf
                                <x-btn type="submit" variant="primary" size="md" data-pending-button>
                                    <span data-pending-text>Publish course</span>
                                </x-btn>
                            </form>
                        @else
                            <form
                                method="POST"
                                action="{{ route('instructor.courses.unpublish', $course) }}"
                                data-confirm="Unpublish this course? It leaves the catalog, and existing access is kept."
                                data-pending
                            >
                                @csrf
                                <x-btn type="submit" variant="secondary" size="md" data-pending-button>
                                    <span data-pending-text>Unpublish course</span>
                                </x-btn>
                            </form>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('instructor.courses.archive', $course) }}"
                            data-confirm="Archive this course? Enrollments and progress are kept."
                            data-pending
                        >
                            @csrf
                            <x-btn type="submit" variant="secondary" size="md" data-pending-button>
                                <span data-pending-text>Archive course</span>
                            </x-btn>
                        </form>
                    @endif
                </div>
            </div>

            @if ($course->status === \App\Enums\CourseStatus::Draft)
                <x-note tone="warning" class="mt-4">
                    Publishing needs at least one Module and one Lesson. Students cannot see a draft course.
                </x-note>
            @endif
        </header>

        @forelse ($course->modules as $module)
            <section class="card mt-8 overflow-hidden" aria-labelledby="module-{{ $module->id }}">
                <div class="flex flex-col gap-3 border-b border-line bg-surface-muted px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="eyebrow">Module {{ $module->position }}</p>
                        <h2 id="module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-balance text-ink">
                            {{ $module->title }}
                        </h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-status :value="$module->status->value" />
                        <x-btn
                            :href="route('instructor.courses.modules.edit', [$course, $module])"
                            variant="secondary"
                            size="sm"
                        >Edit module</x-btn>

                        @if ($module->status === \App\Enums\ContentStatus::Archived)
                            <form method="POST" action="{{ route('instructor.courses.modules.restore', [$course, $module]) }}" data-pending>
                                @csrf
                                <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                    <span data-pending-text>Restore module</span>
                                </x-btn>
                            </form>
                        @else
                            <form
                                method="POST"
                                action="{{ route('instructor.courses.modules.archive', [$course, $module]) }}"
                                data-confirm="Archive this module? Enrollments and progress are kept."
                                data-pending
                            >
                                @csrf
                                <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                    <span data-pending-text>Archive module</span>
                                </x-btn>
                            </form>
                        @endif
                    </div>
                </div>

                @forelse ($module->lessons as $lesson)
                    <article class="border-b border-line px-5 py-5 last:border-b-0" aria-labelledby="lesson-{{ $lesson->id }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                    Lesson {{ $lesson->position }}
                                </p>
                                <h3 id="lesson-{{ $lesson->id }}" class="mt-1 font-semibold text-balance text-ink">
                                    {{ $lesson->title }}
                                </h3>
                                @if ($lesson->summary)
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-muted">{{ $lesson->summary }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <x-status :value="$lesson->status->value" />
                                <x-badge tone="neutral">{{ $lesson->is_required ? 'Required' : 'Optional' }}</x-badge>
                                <x-btn
                                    :href="route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson])"
                                    variant="secondary"
                                    size="sm"
                                >Edit lesson</x-btn>

                                @if ($lesson->status === \App\Enums\ContentStatus::Archived)
                                    <form
                                        method="POST"
                                        action="{{ route('instructor.courses.modules.lessons.restore', [$course, $module, $lesson]) }}"
                                        data-pending
                                    >
                                        @csrf
                                        <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                            <span data-pending-text>Restore lesson</span>
                                        </x-btn>
                                    </form>
                                @else
                                    <form
                                        method="POST"
                                        action="{{ route('instructor.courses.modules.lessons.archive', [$course, $module, $lesson]) }}"
                                        data-confirm="Archive this lesson? Student progress on it is kept."
                                        data-pending
                                    >
                                        @csrf
                                        <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                            <span data-pending-text>Archive lesson</span>
                                        </x-btn>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($lesson->learningMaterials->isNotEmpty())
                            <ul role="list" class="mt-4 grid gap-2 sm:grid-cols-2">
                                @foreach ($lesson->learningMaterials as $material)
                                    <li class="rounded-md border border-line bg-surface-muted px-3 py-3 text-sm">
                                        <p class="font-medium text-ink">{{ $material->title }}</p>
                                        <p class="mt-0.5 text-xs text-ink-muted">
                                            Material {{ $material->position }}
                                            <span aria-hidden="true">Â·</span>
                                            {{ \App\Support\StatusLabel::words($material->material_type->value) }}
                                        </p>
                                        <x-btn
                                            :href="route('instructor.courses.materials.edit', [$course, $module, $lesson, $material])"
                                            variant="secondary"
                                            size="sm"
                                            class="mt-2"
                                        >Edit material</x-btn>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-sm text-ink-muted">No Learning Materials are recorded yet.</p>
                        @endif

                        @php $materialFailed = old('form_context') === 'material:'.$lesson->id; @endphp
                        {{-- A rejected material form reopens with its typed text.
                             The hook keeps that guarantee independent of styling. --}}
                        <details
                            class="mt-4 rounded-md border border-line bg-surface-muted px-4 py-3"
                            data-form-context="material:{{ $lesson->id }}"
                            @if ($materialFailed) open @endif
                        >
                            <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                                <x-icon name="plus" size="sm" />
                                Add material
                            </summary>

                            <form
                                method="POST"
                                action="{{ route('instructor.courses.materials.store', [$course, $module, $lesson]) }}"
                                class="mt-4 space-y-4"
                                data-pending
                            >
                                @csrf
                                <input type="hidden" name="form_context" value="material:{{ $lesson->id }}">

                                <x-form-field
                                    name="title"
                                    label="Material title"
                                    :scope="'material-'.$lesson->id"
                                    :value="$materialFailed ? old('title') : ''"
                                    :maxlength="255"
                                    required
                                />

                                {{-- File types are rejected by the server, so only the
                                     four metadata types appear here. --}}
                                <x-form-field
                                    name="material_type"
                                    label="Material type"
                                    :scope="'material-'.$lesson->id"
                                    type="select"
                                    :value="$materialFailed ? old('material_type') : 'text'"
                                    required
                                    :options="collect(\App\Enums\LearningMaterialType::cases())
                                        ->reject(fn (\App\Enums\LearningMaterialType $type): bool => in_array($type->value, ['image', 'pdf', 'document'], true))
                                        ->mapWithKeys(fn (\App\Enums\LearningMaterialType $type): array => [
                                            $type->value => \App\Support\StatusLabel::words($type->value),
                                        ])
                                        ->all()"
                                    hint="Image, PDF, and Document materials need a file. Files are stored privately and served only to authorized readers."
                                />

                                <x-form-field
                                    name="file"
                                    label="Material file"
                                    :scope="'material-'.$lesson->id"
                                    type="file"
                                    field-class="file:mr-3 file:min-h-11 file:rounded-md file:border-0 file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-ink"
                                    hint="10 MB maximum. Executables and scripts are rejected."
                                />

                                <x-form-field
                                    name="content_text"
                                    label="Material content"
                                    :scope="'material-'.$lesson->id"
                                    type="textarea"
                                    :rows="4"
                                    :value="$materialFailed ? old('content_text') : ''"
                                    :maxlength="100000"
                                    field-class="font-mono"
                                    hint="Required for Text and Code materials."
                                />

                                <x-form-field
                                    name="external_url"
                                    label="Material link"
                                    :scope="'material-'.$lesson->id"
                                    type="url"
                                    :value="$materialFailed ? old('external_url') : ''"
                                    inputmode="url"
                                    :maxlength="2048"
                                    placeholder="https://example.com/page"
                                    hint="Required for Video link and External link materials."
                                />

                                <x-btn type="submit" variant="primary" size="md" data-pending-button>
                                    <span data-pending-text>Add material</span>
                                </x-btn>
                            </form>
                        </details>
                    </article>
                @empty
                    <p class="px-5 py-5 text-sm text-ink-muted">No Lessons are recorded in this Module yet.</p>
                @endforelse

                @php $lessonFailed = old('form_context') === 'lesson:'.$module->id; @endphp
                {{--
                    A rejected lesson form reopens with the typed text still in
                    place. `data-form-context` is the stable hook for that
                    behaviour, so a restyle cannot quietly change it.
                --}}
                <details
                    class="border-t border-line bg-surface-muted px-5 py-4"
                    data-form-context="lesson:{{ $module->id }}"
                    @if ($lessonFailed) open @endif
                >
                    <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                        <x-icon name="plus" size="sm" />
                        Add lesson
                    </summary>

                    <form
                        method="POST"
                        action="{{ route('instructor.courses.modules.lessons.store', [$course, $module]) }}"
                        class="mt-4 space-y-4"
                        data-pending
                    >
                        @csrf
                        <input type="hidden" name="form_context" value="lesson:{{ $module->id }}">

                        <x-form-field
                            name="title"
                            label="Lesson title"
                            :scope="'lesson-'.$module->id"
                            :value="$lessonFailed ? old('title') : ''"
                            :maxlength="160"
                            required
                        />

                        <x-form-field
                            name="summary"
                            label="Summary"
                            type="textarea"
                            :scope="'lesson-'.$module->id"
                            :rows="3"
                            :value="$lessonFailed ? old('summary') : ''"
                            :maxlength="5000"
                        />

                        <x-form-field
                            name="content_text"
                            label="Lesson content"
                            type="textarea"
                            :scope="'lesson-'.$module->id"
                            :rows="4"
                            :value="$lessonFailed ? old('content_text') : ''"
                            :maxlength="100000"
                            hint="Leave a blank line between paragraphs."
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form-field
                                name="estimated_minutes"
                                label="Estimated minutes"
                                type="number"
                                :scope="'lesson-'.$module->id"
                                :value="$lessonFailed ? old('estimated_minutes') : ''"
                                field-class="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                                hint="Optional and positive."
                            />

                            <div>
                                <span class="field-label">Completion</span>
                                {{-- The hidden `0` is submitted first, because a form
                                     keeps the last value for a repeated name. --}}
                                <input type="hidden" name="is_required" value="0">
                                <label
                                    for="lesson-required-{{ $module->id }}"
                                    class="mt-2 flex min-h-12 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 text-sm leading-6 text-ink transition-colors hover:bg-surface-muted has-checked:border-primary has-checked:bg-primary-quiet"
                                >
                                    <input
                                        id="lesson-required-{{ $module->id }}"
                                        name="is_required"
                                        value="1"
                                        type="checkbox"
                                        class="field-check"
                                        @checked($lessonFailed ? (bool) old('is_required', true) : true)
                                    >
                                    <span>
                                        <span class="block font-semibold">Required lesson</span>
                                        <span class="block text-ink-muted">Counts toward course completion.</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <x-btn type="submit" variant="primary" size="md" data-pending-button>
                            <span data-pending-text>Add private lesson</span>
                        </x-btn>
                    </form>
                </details>

                @php
                    $reorderableLessons = $module->lessons
                        ->where('status.value', '!=', \App\Enums\ContentStatus::Archived->value)
                        ->values();
                @endphp

                @if ($reorderableLessons->count() > 1)
                    <details class="border-t border-line px-5 py-4">
                        <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                            <x-icon name="layers" size="sm" />
                            Reorder lessons
                        </summary>

                        <form
                            method="POST"
                            action="{{ route('instructor.courses.modules.lessons.reorder', [$course, $module]) }}"
                            class="mt-4 space-y-3"
                            data-pending
                        >
                            @csrf
                            @method('PATCH')

                            @foreach ($reorderableLessons as $index => $reorderable)
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label for="lesson-position-{{ $reorderable->id }}" class="text-sm font-medium text-ink">
                                        <span class="block text-xs text-ink-muted">Now {{ $reorderable->position }}</span>
                                        <span class="block">{{ $reorderable->title }}</span>
                                    </label>
                                    <input
                                        id="lesson-position-{{ $reorderable->id }}"
                                        name="positions[{{ $reorderable->id }}]"
                                        type="number"
                                        min="1"
                                        max="{{ $reorderableLessons->count() }}"
                                        step="1"
                                        required
                                        value="{{ $index + 1 }}"
                                        class="field-control field-control-surface sm:w-24"
                                    >
                                </div>
                            @endforeach

                            <x-btn type="submit" variant="primary" size="md" data-pending-button>
                                <span data-pending-text>Save lesson order</span>
                            </x-btn>
                        </form>
                    </details>
                @endif
            </section>
        @empty
            <div class="card mt-8">
                <x-empty-state
                    icon="layers"
                    title="No Modules yet"
                    description="This Course is a private draft. Add the first Module to begin its outline."
                />
            </div>
        @endforelse

        <section class="mt-12" aria-labelledby="course-quizzes-heading">
            <h2 id="course-quizzes-heading" class="text-xl font-semibold text-ink">Quizzes</h2>
            <p class="mt-1 text-sm leading-6 text-ink-muted">
                A quiz needs at least one question, and every question needs exactly one correct answer before it can
                be published.
            </p>

            <ul role="list" class="mt-5 space-y-4">
                @forelse ($course->quizzes as $quiz)
                    <li class="card p-5" aria-labelledby="quiz-{{ $quiz->id }}-heading">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="eyebrow">Quiz {{ $quiz->position }}</p>
                                <h3 id="quiz-{{ $quiz->id }}-heading" class="mt-1 font-semibold text-balance text-ink">
                                    {{ $quiz->title }}
                                </h3>
                                @if ($quiz->description)
                                    <p class="mt-1 max-w-2xl text-sm leading-6 text-ink-muted">{{ $quiz->description }}</p>
                                @endif
                                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
                                    <span>{{ $quiz->questions->count() }} {{ \Illuminate\Support\Str::plural('question', $quiz->questions->count()) }}</span>
                                    <span aria-hidden="true">Â·</span>
                                    <span>pass at {{ rtrim(rtrim((string) $quiz->passing_score_percent, '0'), '.') }}%</span>
                                    <span aria-hidden="true">Â·</span>
                                    <span>{{ $quiz->max_attempts }} {{ \Illuminate\Support\Str::plural('attempt', $quiz->max_attempts) }}</span>
                                    @if ($quiz->is_required)
                                        <span aria-hidden="true">Â·</span>
                                        <span>required</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <x-status :value="$quiz->status->value" />

                                @if ($quiz->status === \App\Enums\QuizStatus::Archived)
                                    <span class="text-sm font-semibold text-ink-muted">Archived</span>
                                @else
                                    <form method="POST" action="{{ route('instructor.courses.quizzes.publish', [$course, $quiz]) }}" data-pending>
                                        @csrf
                                        <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                            <span data-pending-text>
                                                {{ $quiz->status === \App\Enums\QuizStatus::Published ? 'Republish' : 'Publish quiz' }}
                                            </span>
                                        </x-btn>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('instructor.courses.quizzes.archive', [$course, $quiz]) }}"
                                        data-confirm="Archive this quiz? Student results are kept."
                                        data-pending
                                    >
                                        @csrf
                                        <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                            <span data-pending-text>Archive quiz</span>
                                        </x-btn>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <details class="mt-4 border-t border-line pt-3">
                            <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                                <x-icon name="clipboard" size="sm" />
                                Questions and options
                            </summary>

                            <ul role="list" class="mt-3 space-y-3">
                                @forelse ($quiz->questions as $question)
                                    <li class="rounded-md border border-line bg-surface-muted p-4">
                                        <p class="font-medium text-ink">{{ $question->prompt }}</p>
                                        <ul role="list" class="mt-2 space-y-1">
                                            @foreach ($question->options as $option)
                                                <li class="flex items-center gap-2 text-sm {{ $option->is_correct ? 'font-semibold text-success-text' : 'text-ink-muted' }}">
                                                    <x-icon :name="$option->is_correct ? 'check-circle' : 'info'" size="sm" class="shrink-0" />
                                                    <span>{{ $option->option_text }}</span>
                                                    @if ($option->is_correct)
                                                        <span class="sr-only">correct answer</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @empty
                                    <li class="text-sm text-ink-muted">No questions yet.</li>
                                @endforelse
                            </ul>

                            <form
                                method="POST"
                                action="{{ route('instructor.courses.quizzes.questions.store', [$course, $quiz]) }}"
                                class="mt-4 space-y-4"
                                data-pending
                            >
                                @csrf

                                <x-form-field
                                    name="prompt"
                                    label="Question prompt"
                                    :scope="'question-'.$quiz->id"
                                    type="textarea"
                                    :rows="2"
                                    :maxlength="5000"
                                    required
                                />

                                <x-form-field
                                    name="points"
                                    label="Points"
                                    :scope="'question-'.$quiz->id"
                                    type="number"
                                    field-class="sm:w-40 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                                />

                                <fieldset>
                                    <legend class="field-label">Options (mark exactly one correct)</legend>

                                    <div class="mt-2 space-y-2">
                                        @for ($slot = 1; $slot <= 4; $slot++)
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                <label for="quiz-{{ $quiz->id }}-option-{{ $slot }}-text" class="sr-only">
                                                    Option {{ $slot }} text
                                                </label>
                                                <input
                                                    id="quiz-{{ $quiz->id }}-option-{{ $slot }}-text"
                                                    name="options[{{ $slot - 1 }}][option_text]"
                                                    type="text"
                                                    maxlength="500"
                                                    class="field-control field-control-surface"
                                                >
                                                <label
                                                    for="quiz-{{ $quiz->id }}-option-{{ $slot }}-correct"
                                                    class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink has-checked:border-primary has-checked:bg-primary-quiet"
                                                >
                                                    <input
                                                        id="quiz-{{ $quiz->id }}-option-{{ $slot }}-correct"
                                                        name="options[{{ $slot - 1 }}][is_correct]"
                                                        type="checkbox"
                                                        value="1"
                                                        @checked($slot === 1)
                                                        class="field-check"
                                                    >
                                                    Correct
                                                </label>
                                            </div>
                                        @endfor
                                    </div>
                                </fieldset>

                                <x-btn type="submit" variant="primary" size="md" data-pending-button>
                                    <span data-pending-text>Add question</span>
                                </x-btn>
                            </form>
                        </details>
                    </li>
                @empty
                    <li class="card p-5 text-sm text-ink-muted">No quizzes in this course yet.</li>
                @endforelse
            </ul>

            @php $quizFailed = old('form_context') === 'quiz'; @endphp
            <details class="card-muted mt-5 p-4" data-form-context="quiz" @if ($quizFailed) open @endif>
                <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                    <x-icon name="plus" size="sm" />
                    Add quiz
                </summary>

                <form
                    method="POST"
                    action="{{ route('instructor.courses.quizzes.store', $course) }}"
                    class="mt-4 space-y-4"
                    data-pending
                >
                    @csrf
                    <input type="hidden" name="form_context" value="quiz">

                    <x-form-field
                        name="title"
                        label="Quiz title"
                        scope="quiz"
                        :value="$quizFailed ? old('title') : ''"
                        :maxlength="255"
                        required
                    />

                    <x-form-field
                        name="description"
                        label="Description"
                        scope="quiz"
                        type="textarea"
                        :rows="2"
                        :value="$quizFailed ? old('description') : ''"
                        :maxlength="5000"
                    />

                    <x-form-field
                        name="module_id"
                        label="Module"
                        type="select"
                        :value="$quizFailed ? old('module_id') : ''"
                        :empty-label="'Whole course'"
                    >
                        @foreach ($course->modules as $courseModule)
                            <option
                                value="{{ $courseModule->id }}"
                                @selected($quizFailed && (int) old('module_id') === $courseModule->id)
                            >Module {{ $courseModule->position }}: {{ $courseModule->title }}</option>
                        @endforeach
                    </x-form-field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form-field
                            name="passing_score_percent"
                            label="Passing score (%)"
                            type="number"
                            :value="80"
                            field-class="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <x-form-field
                            name="max_attempts"
                            label="Maximum attempts"
                            type="number"
                            :value="3"
                            field-class="[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                        />
                    </div>

                    <input type="hidden" name="is_required" value="0">
                    <label
                        for="quiz-required"
                        class="flex min-h-12 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 text-sm leading-6 text-ink transition-colors hover:bg-surface-muted has-checked:border-primary has-checked:bg-primary-quiet"
                    >
                        <input id="quiz-required" name="is_required" value="1" type="checkbox" class="field-check">
                        <span>
                            <span class="block font-semibold">Required for course completion</span>
                            <span class="block text-ink-muted">A student must pass it to claim a certificate.</span>
                        </span>
                    </label>

                    <x-btn type="submit" variant="primary" size="md" data-pending-button>
                        <span data-pending-text>Add private quiz</span>
                    </x-btn>
                </form>
            </details>
        </section>

        @php
            $reorderableModules = $course->modules
                ->where('status.value', '!=', \App\Enums\ContentStatus::Archived->value)
                ->values();
        @endphp
        @if ($reorderableModules->count() > 1)
            <section class="card-muted mt-8 p-5" aria-labelledby="reorder-modules-heading">
                <h2 id="reorder-modules-heading" class="text-lg font-semibold text-ink">Reorder modules</h2>
                <p class="mt-1 text-sm leading-6 text-ink-muted">
                    Enter a position from 1 to {{ $reorderableModules->count() }} for each module. The saved order is
                    the outline order students read.
                </p>

                <x-form-errors :errors="$errors" class="mt-4" />

                <form method="POST" action="{{ route('instructor.courses.modules.reorder', $course) }}" class="mt-4" data-pending>
                    @csrf
                    @method('PATCH')

                    <ul role="list" class="space-y-3">
                        @foreach ($reorderableModules as $index => $reorderable)
                            <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <label for="module-position-{{ $reorderable->id }}" class="text-sm font-medium text-ink">
                                    <span class="block text-xs text-ink-muted">Now {{ $reorderable->position }}</span>
                                    <span class="block">{{ $reorderable->title }}</span>
                                </label>
                                <input
                                    id="module-position-{{ $reorderable->id }}"
                                    name="positions[{{ $reorderable->id }}]"
                                    type="number"
                                    min="1"
                                    max="{{ $reorderableModules->count() }}"
                                    step="1"
                                    required
                                    value="{{ $index + 1 }}"
                                    class="field-control field-control-surface sm:w-24"
                                >
                            </li>
                        @endforeach
                    </ul>

                    <x-btn type="submit" variant="primary" size="lg" class="mt-5" data-pending-button>
                        <span data-pending-text>Save module order</span>
                    </x-btn>
                </form>
            </section>
        @endif

        @php $moduleFailed = old('form_context') === 'module'; @endphp
        <section class="mt-8 border-t border-line pt-8" aria-labelledby="add-module-heading">
            <h2 id="add-module-heading" class="text-lg font-semibold text-ink">Add module</h2>
            <p class="mt-2 text-sm leading-6 text-ink-muted">
                New Modules are private drafts. Their order is assigned by the server.
            </p>

            <form
                method="POST"
                action="{{ route('instructor.courses.modules.store', $course) }}"
                class="card mt-5 max-w-2xl space-y-4 p-5"
                data-pending
            >
                @csrf
                <input type="hidden" name="form_context" value="module">

                <x-form-field
                    name="title"
                    label="Module title"
                    scope="module"
                    :value="$moduleFailed ? old('title') : ''"
                    :maxlength="160"
                    required
                />

                <x-form-field
                    name="description"
                    label="Module description"
                    scope="module"
                    type="textarea"
                    :rows="3"
                    :value="$moduleFailed ? old('description') : ''"
                    :maxlength="5000"
                />

                <x-btn type="submit" variant="primary" size="md" data-pending-button>
                    <span data-pending-text>Add private module</span>
                </x-btn>
            </form>
        </section>
    </div>
@endsection
