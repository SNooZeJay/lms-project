@props([
    'courses',
    'moreHref' => null,
    'moreLabel' => null,
])

{{--
    A row of course cards.

    THE COLUMN COUNT FOLLOWS THE NUMBER OF CARDS

    This was fixed at three columns whatever it was given, on the grounds that a
    row which is not completely full looks deliberate and a card which changes
    width between two sections on the same page looks like a mistake. The second
    half of that is right and the first half is wrong.

    With three columns and two courses, a third of the row was simply empty, and
    an unexplained gap in the middle of a page is the single most noticeable thing
    on it. A reader does not think "this is a deliberate rhythm". They think
    something failed to load.

    So the count follows the cards, up to three, and a group of two is a group of
    two. To stop the two wide cards becoming the problem the first version was
    avoiding, the whole grid is capped at the width two or three columns actually
    need, so a pair sits beside each other at a card's width rather than
    stretching to fill a desktop.

    THE LAST CELL, WHEN THERE IS ROOM FOR ONE

    Where a group is short by exactly one card, the empty cell carries a link to
    the full list rather than being left blank. One quiet line and an arrow, in
    the same card metrics as its neighbours so the row still reads as a row.

    This is the only fill, and it is deliberate rather than automatic: a group of
    one free course is not padded out to three. One card is one card, and the page
    should be honest that the catalog is small.

    THE GAP

    The same at every size. A grid that is tighter on a phone looks designed, and
    a grid that is wider on a desktop separates cards that belong together.
--}}
@php
    $count = $courses->count();

    // Three columns for three or more, two for two, and one below that. The grid
    // itself is capped so a short row does not spread into oversized cards.
    $columns = match (true) {
        $count >= 2 => 'sm:grid-cols-2 lg:grid-cols-3',
        default => '',
    };

    /*
     | No width cap on the grid.
     |
     | A short group used to be capped so two cards would not stretch to six
     | hundred pixels each, which is true and which solved the problem by creating
     | a worse one: the row stopped two thirds of the way across the container and
     | left a three hundred pixel gap to its right, with the section heading above
     | it running the full width. The gap was the same size as the one the cap was
     | meant to remove, and harder to explain.
     |
     | Three columns with the third cell carrying the link is the answer. The row
     | is full, the two cards are at the width a card is meant to be, and the
     | space is occupied by something a reader can use.
     */
    $width = '';

    /*
     | The empty cell, and when there is one.
     |
     | Only at three columns, and only when the group is exactly one card short of
     | a full row. A group of two is given two columns and has no gap to fill; a
     | group of four is two short, and putting one link in a two wide hole leaves
     | a hole.
     |
     | A group of one is never filled. The catalog holding one free course is
     | simply a small catalog, and padding it out to look full would be the page
     | inventing a shape the data does not have.
     */
    $fill = $moreHref !== null && $count >= 2 && $count % 3 !== 0;
@endphp

<ul {{ $attributes->merge(['class' => trim('grid gap-5 '.$columns.' '.$width)]) }} role="list">
    @foreach ($courses as $course)
        {{--
            Eager for the first card only.

            The first row is the one usually on screen when this page arrives, and a
            deferred image already in the viewport loads late for nothing anybody can
            see. The rest are lazy, because a section of six courses is six
            photographs and only the first is wanted at once.

            The card decides nothing about this: it is lazy unless a page tells it
            otherwise, and this is the page that knows the layout.
        --}}
        <x-course-card :course="$course" :eager="$loop->first" />
    @endforeach

    @if ($fill)
        {{--
            The empty cell, filled.

            Centred rather than spread. With the line at the top and the link at
            the bottom of a full height cell, the middle was an empty rectangle
            and the cell read as something that had failed to load, which is the
            opposite of the intent. Centred, with the same icon square the
            capability and role cards use, it reads as a deliberate card of its
            own and the row reads as three cards rather than two and a gap.

            The link is a real destination and has a name a screen reader will
            read, rather than an arrow floating on its own.
        --}}
        <li class="card hidden lg:flex">
            <a
                href="{{ $moreHref }}"
                class="flex h-full w-full flex-col items-center justify-center gap-4 p-6 text-center transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus focus-visible:ring-inset"
            >
                <span class="flex size-9 items-center justify-center rounded-md border border-line bg-surface-muted text-ink-muted">
                    <x-icon name="book-open" size="sm" />
                </span>

                <span class="text-sm font-semibold text-ink">
                    {{ $moreLabel ?? 'Browse every course in the catalog.' }}
                </span>

                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text">
                    Browse all
                    <x-icon name="arrow-right" size="sm" />
                </span>
            </a>
        </li>
    @endif
</ul>
