@extends('layouts.app')

@section('title', 'IT Learning Hub')
@section('description', 'Courses in information technology, programming, web development, and cybersecurity, with lessons, quizzes, and a certificate at the end.')

@section('content')
    {{--
        The public home page.

        One container, one band rhythm, one measure. Those live in the stylesheet
        as `.shell`, `.band` and `.measure`, and every band on this page is
        assembled from the same three parts: a heading block, a gap, and a body.
        The rhythm used to be written out band by band and had already drifted by a
        step in three places, which is why the page felt longer than the content
        in it.

        Every number here is counted from published courses and published content.
        There is no testimonial, no enrolment figure, and no claim about something
        the application does not do, because an untrue claim on a landing page is
        the cheapest way to look finished and the most expensive way to be wrong.

        THE ORDER

        What this is, then what is in it, then how a single course is finished,
        then who teaches here, then the one thing to do next. Each band answers
        the question the band above it raised, and the page ends on the only band
        that asks for anything, because a page should not ask before it has shown
        what it is.
    --}}

    @php
        $primaryAction = \App\Support\RoleBasedDestination::learningFor(auth()->user());

        $published = $totals['courses'];

        /*
         | Four figures about the catalog, counted, not described.
         |
         | They used to sit in a panel of tinted boxes beside the headline, which
         | is the shape an analytics screen uses. On a page about a library of
         | courses it read as though the site were showing a reader its own
         | internals, and it was the reason the opening of the page felt like a
         | dashboard. They are the same figures on one ruled line now, at the
         | weight of ordinary text.
         */
        $figures = [
            ['label' => 'Free courses', 'value' => $totals['free']],
            ['label' => 'Paid courses', 'value' => $totals['paid']],
            ['label' => 'Lessons', 'value' => $totals['lessons']],
            ['label' => 'Quizzes', 'value' => $totals['quizzes']],
        ];

        /*
         | What a student can do, in the order the platform does it to them.
         |
         | Six items in a sequence rather than six things in a set: find, read,
         | check, track, finish, ask. Every one names a screen somebody can open.
         */
        $capabilities = [
            ['icon' => 'search', 'title' => 'Find a course', 'body' => 'Browse the catalog and compare courses by subject, level, length and price before you commit to anything.'],
            ['icon' => 'book-open', 'title' => 'Read the lessons', 'body' => 'Work through each lesson at your own pace, with the files the instructor attached, and mark it complete when you are done.'],
            ['icon' => 'clipboard', 'title' => 'Check yourself', 'body' => 'Take a quiz as many times as you like, then read back which answers were right and which were not.'],
            ['icon' => 'target', 'title' => 'Watch your progress', 'body' => 'A percentage for the course, worked out from the lessons you have finished and the quizzes you have passed.'],
            ['icon' => 'award', 'title' => 'Take the certificate', 'body' => 'Finish a course and a certificate is issued with your name, the course, the date and a code you can quote.'],
            ['icon' => 'message-square', 'title' => 'Ask a question', 'body' => 'Message an instructor privately, and read the notices that are posted for everybody.'],
        ];

        // The three steps, because that is genuinely how long it takes and no more.
        $steps = [
            ['step' => 'Find a course', 'body' => 'Browse the catalog. Each course lists its modules, lessons, level and price before you commit to anything.'],
            ['step' => 'Work through the lessons', 'body' => 'Read at your own pace and mark each lesson complete. Your progress is saved as you go.'],
            ['step' => 'Finish and take the certificate', 'body' => 'Pass the required quizzes, and the certificate is issued with a code you can show.'],
        ];

        /*
         | What an instructor does, in the order they do it.
         |
         | Four, because this is a sequence and three was not enough to cover it.
         | They were presented as three equal capabilities with three icons, which
         | is a list of things rather than a process, and an instructor reading it
         | could not tell what order they would be doing the work in. The last
         * step is the one that is actually different from the others, and it was
         * missing entirely.
         */
        $workflow = [
            ['title' => 'Write it', 'body' => 'Set the title, subject, level and price. Keep it as a draft for as long as it takes.'],
            ['title' => 'Build it', 'body' => 'Add modules and lessons, put them in order, and attach materials to any lesson.'],
            ['title' => 'Set the questions', 'body' => 'Write the quizzes and mark which of them a student has to pass to finish.'],
            ['title' => 'Publish it', 'body' => 'The catalog only lists a course once it is published, so nothing is advertised unfinished.'],
        ];
    @endphp

    <div class="shell">

        {{--
            The opening.

            A text hero across the full width of the container, with the figures on
            a ruled line beneath it. It was a seven and five split with a panel in
            the right hand five columns, which left the left hand side short of the
            right edge of the page and gave the headline a column it did not need.

            A wider measure is the point. A headline that stops at two thirds of
            the page with a box beside it is a dashboard; the same headline across
            the whole measure is a page.

            This band does animate, and it was left un-animated for a while on
            the grounds that the first thing a reader sees should not be a gap.
            That reasoning was half right and the conclusion was wrong: with the
            opening marked out, nothing on the page moved at all until the reader
            scrolled, because every other band is below the fold. A page that is
            completely still until you scroll is indistinguishable from a page
            whose animation is broken, which is exactly how it read.

            So it is marked, and it is marked faster than the rest. It is on
            screen when the page arrives, so anything that makes the reader wait
            for it is a performance complaint about the site dressed as a
            greeting. Four hundred and twenty milliseconds, against five hundred
            for a band the reader has to scroll to reach.
        --}}
        <section
            class="band-first"
            aria-labelledby="home-heading"
            data-motion="cascade"
        >
            <p class="eyebrow" data-motion="heading">Information Technology learning</p>

            <h1
                id="home-heading"
                class="mt-4 max-w-4xl text-4xl leading-[1.08] font-[650] tracking-tight text-balance text-ink sm:text-5xl lg:text-[3.5rem]"
                data-motion="heading"
            >
                Learn IT by building the skills the work asks for.
            </h1>

            <p class="measure mt-6 text-lg leading-8 text-ink-muted" data-motion="lede">
                Courses in information technology, programming, web development and
                cybersecurity. Read the lessons at your own pace, check yourself with
                a quiz, and take the certificate when you finish.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <x-btn :href="route($primaryAction['route'])" variant="primary" size="lg">
                    {{ $primaryAction['label'] }}
                    <x-icon name="arrow-right" size="sm" />
                </x-btn>

                @guest
                    <x-btn :href="route('login')" variant="secondary" size="lg">
                        <x-icon name="lock" size="sm" />
                        Sign in
                    </x-btn>
                @endguest
            </div>

            {{--
                The figures.

                `data-count-to` carries the real value, which the server has also
                written as the text. The script counts up to it and can only ever
                land on it, so a figure that fails to animate is a figure that is
                still correct.
            --}}
            <dl class="figures mt-12 border-t border-line pt-8">
                @foreach ($figures as $figure)
                    <div>
                        <dt>{{ $figure['label'] }}</dt>
                        <dd data-count-to="{{ $figure['value'] }}">
                            {{ number_format($figure['value'], 0, '.', ',') }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Free and paid are separated because they are decided differently, not
             to make the page look like a shop. --}}
        @if ($freeCourses->isNotEmpty())
            <x-home.section
                id="free-heading"
                title="Start without paying"
                description="These courses are open to anyone who enrolls, and no payment is involved at any point."
                data-motion="on-scroll"
            >
                <x-slot:action>
                    <x-btn :href="route('courses.index', ['course_type' => 'free'])" variant="quiet" size="sm">
                        All free courses
                        <x-icon name="arrow-right" size="sm" />
                    </x-btn>
                </x-slot:action>

                <x-home.course-grid
                    :courses="$freeCourses"
                    class="mt-8"
                    more-href="{{ route('courses.index', ['course_type' => 'free']) }}"
                    more-label="Every free course in the catalog, with its modules and lesson count."
                />
            </x-home.section>
        @endif

        @if ($paidCourses->isNotEmpty())
            <x-home.section
                id="paid-heading"
                title="Longer courses, once paid"
                description="More lessons and a practical project at the end. You pay once, and the course opens as soon as the payment is confirmed."
                data-motion="on-scroll"
            >
                <x-slot:action>
                    <x-btn :href="route('courses.index', ['course_type' => 'paid'])" variant="quiet" size="sm">
                        All paid courses
                        <x-icon name="arrow-right" size="sm" />
                    </x-btn>
                </x-slot:action>

                <x-home.course-grid
                    :courses="$paidCourses"
                    class="mt-8"
                    more-href="{{ route('courses.index', ['course_type' => 'paid']) }}"
                    more-label="Every paid course in the catalog, with its modules and lesson count."
                />
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

        {{--
            What a student can do.

            Six items, but not six equal cards. A grid of six identical cards says
            "here are six features", and the reader skims it. These are the six
            things that happen to a student in order, so they are numbered, and
            the numbers are the only decoration: no tinted boxes, no icon plates,
            just a figure, a name and a sentence on a ruled row.

            Two columns, not three, so each item gets a measure a sentence fits
            into. At three columns the body text ran to about forty five
            characters a line, which is narrow enough to break a sentence into an
            awkward shape twice a row.
        --}}
        <x-home.section
            id="capabilities-heading"
            title="What a student can do"
            description="Six things, each of them a screen in the application, in the order the platform does them to you."
            data-motion="on-scroll"
        >
            <ol role="list" class="mt-8 grid gap-x-12 gap-y-8 sm:grid-cols-2">
                @foreach ($capabilities as $index => $item)
                    <li class="border-t border-line pt-5">
                        <span class="font-mono text-xs font-semibold text-primary-text tabular-nums">
                            {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                        </span>

                        <h3 class="mt-3 flex items-center gap-2 text-base leading-6 font-semibold text-ink">
                            <x-icon :name="$item['icon']" size="sm" class="text-ink-subtle" />
                            {{ $item['title'] }}
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $item['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </x-home.section>

        {{-- Three steps, because that is genuinely how long it takes and no more.
             The rule between the markers is drawn by the list, so it is one line
             running behind the numbers rather than three rules kept in step. --}}
        <x-home.section
            id="how-heading"
            title="From a course to a certificate"
            description="Three steps, and the whole of it."
            data-motion="on-scroll"
        >
            <ol role="list" class="steps mt-10">
                @foreach ($steps as $index => $item)
                    <li>
                        <span class="step-marker" aria-hidden="true">{{ $index + 1 }}</span>

                        <div class="md:mt-5">
                            <h3 class="text-base leading-6 font-semibold text-ink">{{ $item['step'] }}</h3>
                            <p class="mt-2 max-w-xs text-sm leading-6 text-ink-muted">{{ $item['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-home.section>

        {{--
            Teaching.

            The other half of the audience. A student is the reader this page is
            written for, but an instructor opening the same link is deciding
            whether this is a place they can teach, and a page that never mentions
            teaching answers that by omission.

            Four numbered steps rather than three capabilities, because it is the
            order the work happens in and an instructor needs to see that order to
            recognise their own week.

            The action is role aware in three cases rather than two. A guest is
            sent to sign in, because the instructor area sits behind
            authentication. An instructor is sent to the area itself. A reader who
            is signed in but is not an instructor is given no button at all: no
            page turns a student into an instructor, so the honest thing is an
            empty space rather than a dead end.
        --}}
        <x-home.section
            id="teaching-heading"
            title="If you teach here"
            description="An instructor writes the course, arranges it, decides what has to be passed, and publishes it when it is ready."
            data-motion="on-scroll"
        >
            <x-slot:action>
                @if (auth()->user()?->profile?->role === \App\Enums\UserRole::Instructor)
                    <x-btn :href="route('instructor.courses.create')" variant="secondary" size="sm">
                        Create a course
                        <x-icon name="plus" size="sm" />
                    </x-btn>
                @elseif (auth()->guest())
                    <x-btn :href="route('login')" variant="secondary" size="sm">
                        <x-icon name="lock" size="sm" />
                        Sign in to teach
                    </x-btn>
                @endif
            </x-slot:action>

            <ol role="list" class="workflow mt-10">
                @foreach ($workflow as $index => $step)
                    <li class="border-t border-line pt-5">
                        <span class="step-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>

                        <h3 class="mt-3 text-base leading-6 font-semibold text-ink">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </x-home.section>

        {{--
            The finish.

            A border and a tinted ground, like every other panel on the page. This
            was a full width panel in the deep brand blue with white buttons on it,
            and it was the only dark surface on a page of light ones, so it read as
            a component dropped in from somewhere else rather than as the end of
            this page. The heading is in the primary colour, which is enough to
            mark it as a conclusion without changing the surface underneath it.
        --}}
        <section class="band-last" aria-labelledby="closing-heading" data-motion="on-scroll">
            <div class="panel-call">
                <div class="grid items-center gap-8 lg:grid-cols-12 lg:gap-10">
                    <div class="lg:col-span-7">
                        <h2 id="closing-heading" class="text-2xl font-semibold text-balance text-primary-text sm:text-3xl" data-motion="heading">
                            Open a course and start
                        </h2>
                        <p class="measure mt-3 leading-7 text-ink-muted">
                            The catalog can be read without an account. Signing in is
                            what keeps your progress, lets you take a quiz, and issues
                            the certificate at the end.
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap lg:col-span-5 lg:justify-end">
                        <x-btn :href="route($primaryAction['route'])" variant="primary" size="lg">
                            {{ $primaryAction['label'] }}
                            <x-icon name="arrow-right" size="sm" />
                        </x-btn>

                        <x-btn
                            :href="auth()->guest() ? route('login') : route('courses.index')"
                            variant="secondary"
                            size="lg"
                        >
                            @if (auth()->guest())
                                <x-icon name="lock" size="sm" />
                                Sign in
                            @else
                                Browse the catalog
                            @endif
                        </x-btn>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
