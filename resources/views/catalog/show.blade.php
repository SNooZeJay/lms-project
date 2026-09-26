@extends('layouts.app')

@section('title', $course->title)
@section('description', $course->description ?: 'A published course on IT Learning Hub.')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'Course catalog', 'href' => route('courses.index')],
            ['label' => $course->title],
        ]" class="mb-6" />

        <header class="border-b border-line pb-8">
            <div class="flex flex-wrap items-center gap-2">
                <p class="eyebrow">Published course</p>
                <x-badge :tone="$course->course_type->value === 'free' ? 'success' : 'primary'">
                    {{ $course->course_type->value === 'free' ? 'Free' : 'Paid' }}
                </x-badge>
            </div>

            <h1 class="mt-3 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
                {{ $course->title }}
            </h1>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-badge tone="neutral">{{ \App\Support\StatusLabel::words($course->level->value) }}</x-badge>
                <x-badge tone="neutral">{{ $course->category ?: 'Uncategorized' }}</x-badge>
            </div>

            @if ($course->description)
                <p class="prose-measure mt-5 text-lg leading-8 text-ink-muted">{{ $course->description }}</p>
            @endif

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
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
                    <dt class="meta-label">Instructor</dt>
                    <dd class="meta-value">{{ $course->instructor?->name ?? 'IT Learning Hub' }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Modules</dt>
                    <dd class="meta-value tabular-nums">{{ $course->modules->count() }}</dd>
                </div>
                <div>
                    <dt class="meta-label">Published</dt>
                    <dd class="meta-value">{{ $course->published_at?->format('M j, Y') ?? 'Recently' }}</dd>
                </div>
            </dl>
        </header>

        {{-- The enrollment action. It reflects the real enrollment and payment
             state, and a guest never sees enrollment detail. --}}
        <section class="mt-8" aria-labelledby="enrollment-heading">
            <h2 id="enrollment-heading" class="sr-only">Enroll in this course</h2>

            @if ($enrollmentState === 'enrolled')
                <x-note tone="success">
                    <span class="font-semibold">Enrolled.</span>
                    Open the course to read its published lessons.
                </x-note>

                <x-btn
                    :href="route('student.courses.show', $course)"
                    variant="primary"
                    size="lg"
                    class="mt-4"
                >
                    <x-icon name="book-open" size="sm" />
                    Open course
                </x-btn>
            @elseif ($enrollmentState === 'can_enroll')
                <div class="card-accent-edge flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm leading-6 text-ink">
                        This course is free. Enroll to start your learning record.
                    </p>
                    <form method="POST" action="{{ route('student.enrollments.store', $course) }}" data-pending>
                        @csrf
                        <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                            <span data-pending-text>Enroll free</span>
                        </x-btn>
                    </form>
                </div>
            @elseif ($enrollmentState === 'guest')
                <x-note tone="neutral">
                    <a href="{{ route('login') }}" class="link">Sign in to enroll</a>
                    and open lesson content after enrollment. Browsing needs no account.
                </x-note>
            @elseif ($enrollmentState === 'paid')
                <div class="card-accent-edge flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-ink">
                            This course is paid at
                            <x-amount :minor="$course->price_minor" :type="$course->course_type" :currency="$course->currency" />.
                        </p>
                        <p class="mt-1 text-sm leading-6 text-ink-muted">
                            Lesson content and materials stay locked until a payment is confirmed.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('student.enrollments.store', $course) }}" data-pending>
                        @csrf
                        <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                            <span data-pending-text>Enroll to pay</span>
                        </x-btn>
                    </form>
                </div>
            @elseif ($enrollmentState === 'awaiting_payment')
                <div class="card-accent-edge flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-ink">Waiting for payment confirmation.</p>
                        <p class="mt-1 text-sm leading-6 text-ink-muted">
                            Finish the payment to open the course. Access starts as soon as the payment provider
                            confirms it.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('student.payments.checkout', $course) }}" data-pending>
                        @csrf
                        <x-btn type="submit" variant="primary" size="lg" data-pending-button>
                            <span data-pending-text>Continue payment</span>
                        </x-btn>
                    </form>
                </div>
            @elseif ($enrollmentState === 'inactive')
                <x-note tone="neutral">
                    This enrollment does not grant access yet. Contact an administrator for help.
                </x-note>
            @else
                <x-note tone="neutral">Lesson content and materials unlock after a student enrolls.</x-note>
            @endif
        </section>

        @if ($course->learning_objectives)
            <section class="mt-12" aria-labelledby="objectives-heading">
                <h2 id="objectives-heading" class="text-xl font-semibold text-ink">What you will learn</h2>
                <p class="prose-measure mt-3 leading-7 whitespace-pre-line text-ink-muted">
                    {{ $course->learning_objectives }}
                </p>
            </section>
        @endif

        {{-- The outline structure only. A public page never shows lesson
             content, a lesson summary, or any material data. --}}
        <section class="mt-12" aria-labelledby="outline-heading">
            <h2 id="outline-heading" class="text-xl font-semibold text-ink">Course outline</h2>
            <p class="mt-2 text-sm leading-6 text-ink-muted">
                Module and lesson titles only. Lesson content and materials stay private until you enroll.
            </p>

            @forelse ($course->modules as $module)
                <section class="card mt-4 overflow-hidden" aria-labelledby="catalog-module-{{ $module->id }}">
                    <div class="border-b border-line bg-surface-muted px-5 py-4">
                        <p class="eyebrow">Module {{ $module->position }}</p>
                        <h3 id="catalog-module-{{ $module->id }}" class="mt-1 text-lg font-semibold text-ink">
                            {{ $module->title }}
                        </h3>
                    </div>

                    @if ($module->lessons->isEmpty())
                        <p class="px-5 py-5 text-sm text-ink-muted">No published lessons in this module yet.</p>
                    @else
                        <ol role="list" class="divide-y divide-line">
                            @foreach ($module->lessons as $lesson)
                                <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                            Lesson {{ $lesson->position }}
                                        </p>
                                        <p class="mt-1 font-semibold text-ink">{{ $lesson->title }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <x-badge tone="neutral">{{ $lesson->is_required ? 'Required' : 'Optional' }}</x-badge>
                                        @if ($lesson->estimated_minutes)
                                            <x-badge tone="neutral">{{ $lesson->estimated_minutes }} min</x-badge>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @empty
                <div class="card mt-4">
                    <x-empty-state
                        compact
                        icon="book-open"
                        title="This course has no published outline yet"
                        description="The outline appears here as soon as the Instructor publishes the modules and lessons."
                    />
                </div>
            @endforelse
        </section>
    </div>
@endsection
