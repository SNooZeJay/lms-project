@extends('layouts.app')

@section('title', 'IT Learning Hub')
@section('description', 'Courses in information technology, programming, web development, and cybersecurity, with lessons, quizzes, and a certificate at the end.')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">

        {{-- The hero states what the site is and offers the one thing a visitor
             can do. A signed in student is sent to their own work instead, because
             the catalog is not what they came back for. --}}
        <section class="grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-12 lg:gap-12 lg:py-20" aria-labelledby="home-heading">
            <div class="lg:col-span-7">
                <h1
                    id="home-heading"
                    class="max-w-2xl text-4xl leading-[1.1] font-[650] tracking-tight text-balance text-ink sm:text-5xl"
                >
                    Learn IT. Build practical skills.
                </h1>

                <p class="prose-measure mt-6 text-lg leading-8 text-ink-muted">
                    Courses for information technology, programming, web development, and
                    cybersecurity. Work through the lessons at your own pace, check
                    yourself with a quiz, and take the certificate when you finish.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    @auth
                        <x-btn :href="route('student.courses.index')" variant="primary" size="lg">
                            My courses
                            <x-icon name="arrow-right" size="sm" />
                        </x-btn>
                    @else
                        <x-btn :href="route('courses.index')" variant="primary" size="lg">
                            Browse courses
                            <x-icon name="arrow-right" size="sm" />
                        </x-btn>

                        <x-btn :href="route('login')" variant="secondary" size="lg">
                            <x-icon name="lock" size="sm" />
                            Sign in
                        </x-btn>
                    @endauth
                </div>
            </div>

            {{-- What a course actually contains, taken from the catalog rather
                 than described. This is the space a marketing panel would occupy,
                 and real numbers are more use to a visitor than a slogan. --}}
            <aside class="lg:col-span-5" aria-labelledby="catalog-summary-heading">
                <div class="card p-6">
                    <h2 id="catalog-summary-heading" class="text-sm font-semibold text-ink">
                        In the catalog now
                    </h2>

                    <dl class="mt-4 grid grid-cols-2 gap-4">
                        <div class="rounded-lg bg-surface-muted px-4 py-3">
                            <dt class="text-xs text-ink-muted">Free courses</dt>
                            <dd class="mt-1 text-2xl font-[650] text-ink tabular-nums">
                                {{ $freeCourses->count() }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-surface-muted px-4 py-3">
                            <dt class="text-xs text-ink-muted">Paid courses</dt>
                            <dd class="mt-1 text-2xl font-[650] text-ink tabular-nums">
                                {{ $paidCourses->count() }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-4 flex items-start gap-2 text-sm leading-6 text-ink-muted">
                        <x-icon name="info" size="sm" class="mt-1 shrink-0 text-ink-subtle" />
                        <span>
                            Every course lists its modules, lessons, and level before you
                            enroll, and a free one needs no payment.
                        </span>
                    </p>
                </div>
            </aside>
        </section>

        {{-- Free and paid are separated because they are decided differently, not
             to make the page look like a shop. --}}
        @if ($freeCourses->isNotEmpty())
            <section class="border-t border-line py-14" aria-labelledby="free-heading">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-xl">
                        <h2 id="free-heading" class="text-2xl font-semibold text-ink">
                            Free courses
                        </h2>
                        <p class="mt-2 leading-7 text-ink-muted">
                            Start these without paying anything.
                        </p>
                    </div>

                    <x-btn :href="route('courses.index', ['course_type' => 'free'])" variant="quiet" size="sm">
                        All free courses
                        <x-icon name="arrow-right" size="sm" />
                    </x-btn>
                </div>

                <ul role="list" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($freeCourses as $course)
                        <x-course-card :course="$course" />
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($paidCourses->isNotEmpty())
            <section class="border-t border-line py-14" aria-labelledby="paid-heading">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-xl">
                        <h2 id="paid-heading" class="text-2xl font-semibold text-ink">
                            Paid courses
                        </h2>
                        <p class="mt-2 leading-7 text-ink-muted">
                            Longer courses with more lessons and a practical project at the
                            end. You pay once, and the course opens as soon as the payment
                            is confirmed.
                        </p>
                    </div>

                    <x-btn :href="route('courses.index', ['course_type' => 'paid'])" variant="quiet" size="sm">
                        All paid courses
                        <x-icon name="arrow-right" size="sm" />
                    </x-btn>
                </div>

                <ul role="list" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($paidCourses as $course)
                        <x-course-card :course="$course" />
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($freeCourses->isEmpty() && $paidCourses->isEmpty())
            <section class="border-t border-line py-14" aria-labelledby="empty-catalog-heading">
                <x-empty-state
                    title="No published courses yet"
                    description="Courses appear here as soon as an instructor publishes one."
                />
            </section>
        @endif

        {{-- Three steps, because that is genuinely how long it takes and no more. --}}
        <section class="border-t border-line py-14" aria-labelledby="how-heading">
            <h2 id="how-heading" class="text-2xl font-semibold text-ink">
                How it works
            </h2>

            <ol role="list" class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['icon' => 'search', 'step' => 'Find a course', 'body' => 'Browse the catalog. Each course lists its modules, lessons, level, and price before you commit to anything.'],
                    ['icon' => 'book-open', 'step' => 'Work through the lessons', 'body' => 'Read at your own pace and mark each lesson complete. Your progress is saved as you go.'],
                    ['icon' => 'certificate', 'step' => 'Finish and take the certificate', 'body' => 'Pass the required quizzes, and the certificate is issued with a code you can show.'],
                ] as $index => $item)
                    <li class="flex gap-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-quiet font-mono text-sm font-semibold text-primary-text tabular-nums">
                            {{ $index + 1 }}
                        </span>
                        <div>
                            <h3 class="font-semibold text-ink">{{ $item['step'] }}</h3>
                            <p class="mt-1.5 text-sm leading-6 text-ink-muted">{{ $item['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection
