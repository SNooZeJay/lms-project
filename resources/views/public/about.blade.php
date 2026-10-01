@extends('layouts.app')

@section('title', 'About IT Learning Hub')
@section('description', 'What IT Learning Hub is, who it is for, and what a student or an instructor can do with it.')

@section('content')
    {{--
        The About page.

        WHO THIS IS WRITTEN FOR

        A student, an instructor, or anybody who has found the site and wants to
        know what it is before deciding whether to make an account. Not somebody
        reading the repository.

        That distinction decided the content rather than decorating it. An earlier
        version of this page ended with a table naming the framework, the language,
        the database and the build tool. Every line of it was true, every line of
        it was irrelevant, and together they told a reader the one thing they did
        not ask: that the page was written by somebody showing their work rather
        than somebody answering a question. A reader who wanted to know what a
        certificate is got a list of packages instead.

        So the page is about the learning, from beginning to end: what a course is,
        what a student does with one, what an instructor does to write one, what
        has to happen before a certificate is issued, and how people talk to each
        other while it is going on.

        HOW IT IS ARRANGED

        Each band is shaped to what it has to say rather than to one repeated
        template. An opening, a three part chain, a course broken into its parts,
        a band per role, the four conditions for finishing, and a finish. The
        vertical rhythm is `.band`, the same one the home page uses, and prose is
        held to `.measure`, so the two pages read as one site.
    --}}

    @php
        /*
         | The chain a course is made of, outermost first.
         |
         | Three cards, because the structure really is three deep and a single
         | sentence naming all three would teach nothing about how they sit inside
         | each other. A learner is helped by knowing a course is not one long
         | document, because that is what makes progress feel achievable.
         */
        $anatomy = [
            ['icon' => 'book-open', 'term' => 'Course', 'body' => 'One subject, at a level that matches where you are. Each course states its own level, its price or that it is free, and who teaches it, so you know what you are choosing before you start.'],
            ['icon' => 'layers', 'term' => 'Module', 'body' => 'A section of the course holding a group of lessons. Modules are published one at a time, so you can see how far through the material you are and how much is left.'],
            ['icon' => 'folder', 'term' => 'Lesson', 'body' => 'One lesson of reading, with any files the instructor attached to it. You mark it complete when you have finished, and that is what moves your progress forward.'],
        ];

        /*
         | What a student can do, in the order they do it.
         |
         | Six, and the order matters. This is the journey a learner takes, and
         | the band is the answer to "what will I actually be able to do here"
         | rather than a list of features to admire.
         */
        $learning = [
            ['icon' => 'book-open', 'title' => 'Learn at your own pace', 'body' => 'Open a course and read it when it suits you. Nothing is scheduled, nothing expires, and you can stop half way through a lesson and come back to it.'],
            ['icon' => 'folder', 'title' => 'Use the learning materials', 'body' => 'Instructors attach files to any lesson, so notes, exercises and examples sit next to the reading that explains them rather than in a separate place.'],
            ['icon' => 'clipboard', 'title' => 'Test what you have learned', 'body' => 'Take a quiz as many times as you need. Every attempt is marked, and afterwards you can see which answers were right and which were not, with the reason.'],
            ['icon' => 'target', 'title' => 'Watch your progress', 'body' => 'A percentage for the course, worked out from the lessons you have finished and the quizzes you have passed. You always know where you stand.'],
            ['icon' => 'award', 'title' => 'Earn a certificate', 'body' => 'Finish every required lesson and pass every required quiz, and a certificate is issued with your name, the course, the date and a code you can quote.'],
            ['icon' => 'message-square', 'title' => 'Ask when you are stuck', 'body' => 'Message an instructor privately when something is unclear, and read the notices posted for everybody taking the course.'],
        ];

        /*
         | The other side of it, for the instructor.
         */
        $teaching = [
            ['icon' => 'pencil', 'title' => 'Write the course', 'body' => 'Set the title, the subject, the level and the price. Keep it as a draft while you work on it, and it stays invisible to students until you publish it.'],
            ['icon' => 'layers', 'title' => 'Arrange it into lessons', 'body' => 'Add modules and lessons, put them in the order you want them taught, and attach materials to any of them.'],
            ['icon' => 'clipboard', 'title' => 'Decide what has to be passed', 'body' => 'Write the quizzes yourself, with your own questions and answers, and mark each one as required or optional. That choice is what decides when a student has finished.'],
            ['icon' => 'users', 'title' => 'Follow how learners are doing', 'body' => 'See who is enrolled in your courses and how far through each one they are, so you know who might need a hand.'],
        ];

        /*
         | The four conditions, in the order they are checked.
         */
        $rules = [
            'Every required lesson is marked complete. A lesson marked optional is recorded, but it does not hold up the certificate.',
            'Every required quiz is passed. An optional quiz never blocks completion, and a failed attempt does not count as a pass.',
            'A lesson or quiz that is still being written is not part of the requirement, so it cannot hold anybody up.',
            'The certificate is issued at the moment the course is completed, so there is no waiting and no separate step to remember.',
        ];
    @endphp

    <div class="shell">

        {{--
            The opening.

            Marked, and marked faster than the bands below it, for the same reason
            as the home page: this is on screen when the page arrives, so the
            reader should not be made to wait for it, and a page on which nothing
            moves until it is scrolled is indistinguishable from a page whose
            animation is not working.
        --}}
        <section
            class="band-first"
            aria-labelledby="about-heading"
            data-motion="on-scroll"
        >
            <div class="grid items-start gap-10 lg:grid-cols-12 lg:gap-12">
                <div class="lg:col-span-7">
                    <p class="eyebrow" data-motion="heading">About</p>

                    <h1
                        id="about-heading"
                        class="mt-3 max-w-3xl text-4xl leading-[1.12] font-[650] tracking-tight text-balance text-ink sm:text-5xl"
                        data-motion="heading"
                        data-motion-delay="80ms"
                    >
                        A place to learn Information Technology properly.
                    </h1>

                    <p class="measure mt-6 text-lg leading-8 text-ink-muted">
                        IT Learning Hub delivers courses in information technology,
                        programming, web development and cybersecurity. You work
                        through the material at your own pace, check what you have
                        learned along the way, and take a certificate at the end.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <x-btn :href="route('courses.index')" variant="primary" size="lg">
                            Browse the catalog
                            <x-icon name="arrow-right" size="sm" />
                        </x-btn>

                        <x-btn :href="route('login')" variant="secondary" size="lg">
                            <x-icon name="lock" size="sm" />
                            Sign in
                        </x-btn>
                    </div>
                </div>

                {{-- The subjects, as a quiet list. They are nouns, not features, so
                     a list is the honest shape and a card each would give them a
                     weight they have not earned. --}}
                <div class="lg:col-span-5 lg:pt-14">
                    <h2 class="text-sm font-semibold text-ink">What you can study</h2>

                    <ul role="list" class="mt-4 divide-y divide-line border-y border-line">
                        @foreach ([
                            'Information Technology',
                            'Programming',
                            'Web Development',
                            'Cybersecurity',
                        ] as $subject)
                            <li class="flex items-center gap-3 py-3">
                                <x-icon name="check" size="sm" class="shrink-0 text-accent-text" />
                                <span class="text-sm text-ink">{{ $subject }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="measure mt-5 text-sm leading-6 text-ink-muted">
                        Some courses are free and open the moment you enroll. Others
                        are paid once, and open as soon as that payment goes through.
                    </p>
                </div>
            </div>
        </section>

        {{-- The chain, outermost first. --}}
        <x-home.section
            id="anatomy-heading"
            title="How a course is put together"
            description="Three levels, each one inside the last. It is worth knowing, because a course that arrives in pieces is easier to finish than one that arrives all at once."
            data-motion="on-scroll"
        >
            <ul role="list" class="mt-8 grid gap-5 sm:grid-cols-3">
                @foreach ($anatomy as $part)
                    <li class="card flex h-full flex-col p-5">
                        <span class="flex size-9 items-center justify-center rounded-md border border-line bg-surface-muted text-ink-muted">
                            <x-icon :name="$part['icon']" size="sm" />
                        </span>

                        <h3 class="mt-4 text-base leading-6 font-semibold text-ink">{{ $part['term'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $part['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </x-home.section>

        {{-- What a student can do. The heart of the page. --}}
        <x-home.section
            id="learning-heading"
            title="What you can do as a student"
            description="Six things, in the order you would do them."
            data-motion="on-scroll"
        >
            <ol role="list" class="mt-8 grid gap-x-12 gap-y-8 sm:grid-cols-2">
                @foreach ($learning as $index => $item)
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

        {{-- The instructor's side. --}}
        <x-home.section
            id="teaching-heading"
            title="What you can do as an instructor"
            description="You write the course, arrange it, decide what a student has to pass, and publish it when it is ready."
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

            <ol role="list" class="workflow mt-8">
                @foreach ($teaching as $index => $item)
                    <li class="border-t border-line pt-5">
                        <span class="step-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>

                        <h3 class="mt-3 text-base leading-6 font-semibold text-ink">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $item['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </x-home.section>

        {{-- The conditions, as a sequence, because that is how they are checked. --}}
        <x-home.section
            id="rules-heading"
            title="How a course is finished"
            description="Four conditions. They are worth knowing in advance, because they are what decides whether a certificate is issued, and they are the first thing to check when a course will not complete."
            data-motion="on-scroll"
        >
            <ol role="list" class="steps mt-10">
                @foreach ($rules as $index => $rule)
                    <li>
                        <span class="step-marker" aria-hidden="true">{{ $index + 1 }}</span>

                        <div class="md:mt-5">
                            <p class="text-sm leading-6 text-ink-muted">{{ $rule }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-home.section>

        {{-- The finish, in the same flat language as everything else. --}}
        <section class="band-last" aria-labelledby="about-closing-heading" data-motion="on-scroll">
            <div class="panel-call">
                <div class="grid items-center gap-8 lg:grid-cols-12 lg:gap-10">
                    <div class="lg:col-span-7">
                        <h2 id="about-closing-heading" class="text-2xl font-semibold text-balance text-primary-text sm:text-3xl">
                            Start with a free course
                        </h2>
                        <p class="measure mt-3 leading-7 text-ink-muted">
                            Every published course lists its modules, lessons, level and
                            price before you commit to anything, so you can read what is
                            on offer before you decide.
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap lg:col-span-5 lg:justify-end">
                        <x-btn :href="route('courses.index')" variant="primary" size="lg">
                            Browse the catalog
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
                                All courses
                            @endif
                        </x-btn>
                    </div>
                </div>
            </div>

            <p class="measure mt-8 text-sm leading-6 text-ink-subtle">
                IT Learning Hub is run for teaching and demonstration rather than as a
                commercial service, and it is reachable only while the machine running
                it is on. The
                <a href="{{ route('legal.terms') }}" class="link">terms of use</a> and the
                <a href="{{ route('legal.privacy') }}" class="link">privacy notice</a>
                say more about both.
            </p>
        </section>
    </div>
@endsection
