@extends('layouts.app')

@section('title', 'IT Learning Hub')
@section('description', 'Courses in information technology, programming, web development, and cybersecurity, with lessons, quizzes, and a certificate at the end.')

@section('content')
    {{--
        The public home page.

        Two audiences read this page and they arrive with different questions. A
        student wants to know what they can learn and how to start. An instructor
        wants to know whether this is a place they can teach. The page answers
        both, and it says only what the application can actually do.

        Every number here is counted from published courses. There is no
        testimonial, no enrolment figure, and no claim about software that is not
        in this repository, because an untrue claim on a landing page is the
        cheapest way to look finished and the most expensive way to be wrong.

        The vertical rhythm is owned by the section component rather than by this
        file, so every band on the page is separated by the same amount of space
        and the same hairline.
    --}}

    @php
        $isInstructor = auth()->check() && auth()->user()->profile?->role === \App\Enums\UserRole::Instructor;

        $published = $freeCourses->count() + $paidCourses->count();

        // Three steps, because that is genuinely how long it takes.
        $steps = [
            ['step' => 'Find a course', 'body' => 'Browse the catalog. Each course lists its modules, lessons, level, and price before you commit to anything.'],
            ['step' => 'Work through the lessons', 'body' => 'Read at your own pace and mark each lesson complete. Your progress is saved as you go.'],
            ['step' => 'Finish and take the certificate', 'body' => 'Pass the required quizzes, and the certificate is issued with a code you can show.'],
        ];

        // What an instructor can actually do, taken from the screens that exist.
        $teaching = [
            ['icon' => 'pencil', 'title' => 'Write the course', 'body' => 'Set the title, category, level, and price, then publish it or keep it as a draft while you work.'],
            ['icon' => 'layers', 'title' => 'Build the modules', 'body' => 'Add modules and lessons, reorder them, and attach materials to any lesson.'],
            ['icon' => 'clipboard', 'title' => 'Set the quizzes', 'body' => 'Write questions with their answers, and mark which quizzes a student must pass.'],
        ];
    @endphp

    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">

        {{-- The hero states what the site is and offers the one thing a visitor
             can do. A signed in student is sent to their own work instead, because
             the catalog is not what they came back for.

             The top padding is larger than a section's, because the hero is the
             entry to the page and has no hairline above it. Its bottom padding is
             the same as a section's, so the gap between the hero and the first
             band matches the gap between any two bands. Running it to py-20 on
             both sides made that first gap sixteen pixels wider than every other
             one on the page. --}}
        <section class="grid items-center gap-10 pt-12 pb-12 sm:pt-16 sm:pb-14 lg:grid-cols-12 lg:gap-12 lg:pt-20 lg:pb-16" aria-labelledby="home-heading">
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

                {{-- sm:items-center, so the two actions share a centre line at
                     every width. Without it the taller primary button and the
                     secondary one sit on their own text baselines and the row
                     reads as misaligned. --}}
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
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

            {{-- What the catalog actually holds, counted rather than described.
                 This is the space a marketing panel would occupy, and real
                 counts are more use to a visitor than a slogan.

                 grid-cols-2 rather than one column below 360 pixels, so the two
                 figures always share a row and the card is never a tall stack of
                 two numbers. --}}
            <aside class="lg:col-span-5" aria-labelledby="catalog-summary-heading">
                <div class="card h-full p-6">
                    <h2 id="catalog-summary-heading" class="text-sm font-semibold text-ink">
                        In the catalog now
                    </h2>

                    <dl class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-lg bg-surface-muted px-4 py-4">
                            <dt class="text-xs text-ink-muted">Free courses</dt>
                            <dd class="mt-1.5 text-2xl font-[650] text-ink tabular-nums">
                                {{ $freeCourses->count() }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-surface-muted px-4 py-4">
                            <dt class="text-xs text-ink-muted">Paid courses</dt>
                            <dd class="mt-1.5 text-2xl font-[650] text-ink tabular-nums">
                                {{ $paidCourses->count() }}
                            </dd>
                        </div>
                    </dl>

                    {{-- The icon is aligned to the first line of the text rather
                         than centred on the paragraph, so it sits beside the line
                         it introduces. --}}
                    <p class="mt-5 flex items-start gap-2.5 text-sm leading-6 text-ink-muted">
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
             to make the page look like a shop. The column count follows the
             number of cards so a group of two does not leave a hole in a three
             column grid. --}}
        @if ($freeCourses->isNotEmpty())
            <x-home.section
                id="free-heading"
                title="Free courses"
                description="Start these without paying anything."
            >
                <x-slot:action>
                    <x-btn :href="route('courses.index', ['course_type' => 'free'])" variant="quiet" size="sm">
                        All free courses
                        <x-icon name="arrow-right" size="sm" />
                    </x-btn>
                </x-slot:action>

                <x-home.course-grid :courses="$freeCourses" class="mt-8" />
            </x-home.section>
        @endif

        @if ($paidCourses->isNotEmpty())
            <x-home.section
                id="paid-heading"
                title="Paid courses"
                description="Longer courses with more lessons and a practical project at the end. You pay once, and the course opens as soon as the payment is confirmed."
            >
                <x-slot:action>
                    <x-btn :href="route('courses.index', ['course_type' => 'paid'])" variant="quiet" size="sm">
                        All paid courses
                        <x-icon name="arrow-right" size="sm" />
                    </x-btn>
                </x-slot:action>

                <x-home.course-grid :courses="$paidCourses" class="mt-8" />
            </x-home.section>
        @endif

        @if ($published === 0)
            <x-home.section
                id="empty-catalog-heading"
                title="The catalog is empty"
                description="Courses appear here as soon as an instructor publishes one."
            >
                <div class="mt-8">
                    <x-empty-state
                        title="No published courses yet"
                        description="There is nothing to enroll in right now. The catalog fills as courses are published."
                    />
                </div>
            </x-home.section>
        @endif

        {{-- Teaching. The other half of the audience.

             A student is the visitor this page was written for, but an instructor
             opening the same link is deciding whether this is a place they can
             teach, and a page that never mentions teaching answers that by
             omission. Each item below names a screen that exists.

             The action is role aware, in three cases rather than two. A guest is
             sent to sign in, because the instructor area sits behind
             authentication. An instructor is sent to the area itself. A reader
             who is already signed in but is not an instructor is given no button
             at all: no page turns a student into an instructor, the role is
             assigned by an administrator, so the honest thing is to leave the
             space empty rather than offer a dead end. Two cases were not enough,
             and offering a signed in student a "Sign in to teach" button is worse
             than offering nothing. --}}
        <section {{-- Same padding as every other band, so the rhythm does not
                      change when the content beside it does. --}}
            class="border-t border-line py-12 sm:py-14 lg:py-16"
            aria-labelledby="teaching-heading"
        >
            <div class="grid gap-10 lg:grid-cols-12 lg:items-start lg:gap-12">
                <div class="lg:col-span-4">
                    <h2 id="teaching-heading" class="text-2xl font-semibold text-balance text-ink">
                        Teaching here
                    </h2>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-muted lg:max-w-none">
                        An instructor writes the course, arranges it into modules and
                        lessons, and decides what a student has to pass. The catalog
                        only lists a course once it is published.
                    </p>

                    @if ($isInstructor)
                        <div class="mt-6">
                            <x-btn :href="route('instructor.courses.create')" variant="secondary">
                                Create a course
                                <x-icon name="plus" size="sm" />
                            </x-btn>
                        </div>
                    @elseif (auth()->guest())
                        <div class="mt-6">
                            <x-btn :href="route('login')" variant="secondary">
                                <x-icon name="lock" size="sm" />
                                Sign in to teach
                            </x-btn>
                        </div>
                    @endif
                </div>

                {{-- The three capabilities. The icon is a bordered square rather
                     than a circle, so this list does not echo the numbered steps
                     below it and the two stay distinguishable. --}}
                <ul role="list" class="grid gap-8 sm:grid-cols-3 sm:gap-8 lg:col-span-8">
                    @foreach ($teaching as $item)
                        <li>
                            <span class="flex size-9 items-center justify-center rounded-md border border-line bg-surface-muted text-ink-muted">
                                <x-icon :name="$item['icon']" size="sm" />
                            </span>

                            <h3 class="mt-4 text-base leading-6 font-semibold text-ink">{{ $item['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $item['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Three steps, because that is genuinely how long it takes and no more. --}}
        <x-home.section
            id="how-heading"
            title="How it works"
            description="Three steps from finding a course to holding the certificate."
        >
            {{-- A real <ol>, so the order is semantic. The circle is decorative
                 and hidden from a screen reader, which would otherwise announce
                 the number and then read it again as the first word.

                 The circle is one line tall, the same 24 pixels as the line box
                 of the heading beside it. At 36 pixels it was top aligned with
                 the heading and sat 6 pixels below the middle of the first line,
                 because the extra 12 pixels had to go somewhere. Matching the
                 line height makes the two centres agree by construction, with no
                 offset to tune. --}}
            <ol role="list" class="mt-8 grid gap-8 md:grid-cols-3 md:gap-10">
                @foreach ($steps as $index => $item)
                    <li class="flex gap-4">
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary-quiet font-mono text-xs font-semibold text-primary-text tabular-nums"
                            aria-hidden="true"
                        >
                            {{ $index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base leading-6 font-semibold text-ink">{{ $item['step'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $item['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-home.section>
    </div>
@endsection
