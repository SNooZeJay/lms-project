@props([
    'course',
    'compact' => false,
    'eager' => false,
])

@php
    use App\Support\Money;
    use App\Support\StatusLabel;

    $isPaid = $course->course_type === \App\Enums\CourseType::Paid;
    $lessons = (int) $course->published_lessons_count;
    $modules = (int) $course->published_modules_count;
    $price = Money::course($course->price_minor, $course->course_type, $course->currency);
@endphp

{{--
    A course in a list.

    The card carries the four things a student decides on: what it is, what it
    covers, how much of it there is, and what it costs. Nothing is shown that a
    student cannot act on.

    THE HIERARCHY, TOP TO BOTTOM

    Cover, then the subject as an eyebrow above the title rather than a second
    line of small caps underneath it. The subject is the weaker fact of the two,
    and putting it above the title meant the eye met it first and read the pair
    as one flat block. Above, it labels the title instead of competing with it.

    The price is the one number on the card, so it is the one number allowed to be
    large, and it sits on the title line where a price belongs.

    The description is two lines, not three. Three lines of a course description
    is a paragraph nobody finishes, and the card below the description has to hold
    the facts and the action as well.

    The facts are one quiet line, separated by rules rather than by icons. Four
    small icons in a row read as a toolbar and made the card look like a panel of
    controls; four short words with a divider between them read as a sentence
    about the course, which is what they are. The level is dropped from the line
    and carried by the eyebrow beside the subject, which is where a reader looks
    for it.

    WHAT THE CARD DOES NOT SHOW

    The instructor's name, which was a fifth item on that line and is the reason it
    felt crowded. It is still one click away, on the course's own page, and a
    student choosing between two courses is reading the courses, not the people.

    `lift` makes the card say it is a link before it is pressed. It is two pixels
    and a border, it is declared only where a real pointer exists so a tap does
    not leave a card stuck looking selected, and it moves the cover inside it
    slightly, which is what stops the card reading as a flat rectangle that
    happens to be a link.

    A transform does not create a positioned ancestor, so the lift cannot truncate
    the stretched link below. That is not an assumption: the hit area test walks
    the parsed document and asks which ancestors of the title are actually
    positioned, and a transform is not one of them.

    The price is read from the course record and formatted from integer minor
    units, so it can never disagree with what checkout would charge.
--}}
<li {{ $attributes->merge(['class' => 'card lift relative flex h-full flex-col overflow-hidden p-0']) }}>
    <x-course-cover
        :course="$course"
        :width="640"
        :height="360"
        rounded="rounded-none"
        :eager="$eager"
        class="aspect-[16/9] w-full shrink-0"
    />

    <div class="flex flex-1 flex-col p-5">
        {{--
            Deliberately not a positioning context.

            The stretched link on the title resolves against the nearest positioned
            ancestor. This row used to be `relative` as well as the card, so it was
            the nearer one and the overlay stopped at the title and the price
            badge, covering 316 by 26 of a 358 by 314 card. Measured in a browser,
            every other part of the card activated nothing, so tapping a course
            card anywhere but its first row did nothing at all.

            Only the card establishes the context, so the overlay covers the card.
            The badge needs no positioning of its own to sit in the row.
        --}}
        @if (! $compact && filled($course->category))
            <p class="text-xs font-semibold tracking-wide text-primary-text uppercase">
                {{ $course->category }}
            </p>
        @endif

        <div class="{{ $compact ? '' : 'mt-2' }} flex items-start justify-between gap-3">
            <h3 class="text-base leading-snug font-semibold text-balance text-ink">
                <a
                    href="{{ route('courses.show', $course) }}"
                    class="rounded-sm after:absolute after:inset-0 hover:text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                >
                    {{ $course->title }}
                </a>
            </h3>

            <p class="shrink-0 text-base font-semibold text-ink tabular-nums">
                {{ $price }}
            </p>
        </div>

        @if (! $compact && filled($course->description))
            <p class="mt-2 line-clamp-2 text-sm leading-6 text-ink-muted">
                {{ $course->description }}
            </p>
        @endif

        {{--
            The one quiet line of facts, counted from published content only, so a
            draft lesson is never counted towards what a student is being offered.

            A single <span> rather than a <dl> of terms and definitions. These are
            adjectives on one course, not a specification with a name for each
            part, and a definition list announces six separate things where a
            reader sees one sentence.
        --}}
        @unless ($compact)
            <p class="mt-4 flex flex-wrap items-center gap-x-2 text-xs text-ink-subtle">
                @if ($modules > 0)
                    <span>{{ $modules }} {{ \Illuminate\Support\Str::plural('module', $modules) }}</span>
                    <span aria-hidden="true" class="text-line-strong">/</span>
                @endif

                @if ($lessons > 0)
                    <span>{{ $lessons }} {{ \Illuminate\Support\Str::plural('lesson', $lessons) }}</span>
                    <span aria-hidden="true" class="text-line-strong">/</span>
                @endif

                <span>{{ StatusLabel::words($course->level->value) }}</span>
            </p>
        @endunless

        @unless ($compact)
            {{-- Relative, so the button sits above the stretched title link and stays a
                 control of its own rather than part of the title's hit area.

                 `mt-auto` and not `mt-5`. The card is a flex column and the button is
                 the last thing in it, so an auto top margin absorbs whatever space the
                 grid left over and puts the button on the bottom edge. A fixed margin
                 left it floating, and two cards whose titles wrapped onto a different
                 number of lines had their buttons at two different heights in the same
                 row, which reads as a mistake rather than as two courses.

                 `pt-5` rather than `mt-5` so the space survives in a card with no
                 leftover to absorb. In a card that is exactly filled, an auto margin
                 resolves to nothing and the button would touch the facts above it.
                 The padding is the floor and the auto margin is what grows. --}}
            <div class="relative mt-auto pt-5">
                <x-btn :href="route('courses.show', $course)" variant="secondary" size="sm">
                    View course
                </x-btn>
            </div>
        @endunless
    </div>
</li>
