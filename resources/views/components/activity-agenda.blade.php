@props([
    'heading',
    'entries' => [],
    'description' => null,
    'empty' => null,
])

{{--
    Dated activity.

    This application has no scheduling. Nothing stores a due date, a lesson
    time, or an assessment deadline, so there is no honest "coming up" list to
    draw and none is invented here. What this shows instead is what actually
    happened and when, taken from real record timestamps: a certificate issued, a
    course finished, a payment confirmed, a quiz sat, a course published.

    That is why it is a list grouped by day rather than a month grid. A grid
    implies a future the data does not contain, and would be mostly empty cells
    on a young system. A day-grouped list is honest at any size, and it is the
    shape that works on a phone without shrinking anything.

    Entries are grouped in the view rather than in the report, so the grouping
    follows the reader's own day boundary rather than the server's.
--}}
@php
    $grouped = $entries instanceof \Illuminate\Support\Collection
        ? $entries->groupBy(fn (array $entry): string => $entry['at']->format('Y-m-d'))
        : collect($entries)->groupBy(fn (array $entry): string => $entry['at']->format('Y-m-d'));
@endphp

{{--
    An agenda card, built from the same two pieces every other card uses.

    Like the bar chart, this used to place its heading, description and list
    directly inside the section, so the heading sat one pixel from the card's
    border while every other card on the page insets its heading by twenty one
    pixels. Side by side, two cards had their titles on different lines. Measured
    at 1px against 21px.

    card-header and card-body make that alignment structural instead of a padding
    somebody has to remember.
--}}
<section {{ $attributes->merge(['class' => 'card']) }} aria-labelledby="{{ $id = 'agenda-'.substr(md5($heading), 0, 8) }}">
    <div class="card-header">
        <div class="min-w-0">
            <h2 id="{{ $id }}" class="text-base font-semibold text-ink">{{ $heading }}</h2>

            @if ($description)
                <p class="mt-1 text-sm leading-6 text-ink-muted">{{ $description }}</p>
            @endif
        </div>
    </div>

    <div class="card-body">
        @if ($grouped->isEmpty())
            <p class="text-sm leading-6 text-ink-muted">
                {{ $empty ?? 'Nothing has happened here yet.' }}
            </p>
        @else
            <ol class="grid gap-5">
                @foreach ($grouped as $day => $dayEntries)
                    <li>
                        <p class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                            <time datetime="{{ $day }}">{{ \Illuminate\Support\Carbon::parse($day)->format('D, j M Y') }}</time>
                        </p>

                        <ul role="list" class="mt-2 grid gap-2.5">
                            @foreach ($dayEntries as $entry)
                                <li class="flex items-start gap-3">
                                    <span
                                        class="mt-1.5 size-2 shrink-0 rounded-full {{ $entry['tone'] === 'accent' ? 'bg-accent' : 'bg-primary' }}"
                                        aria-hidden="true"
                                    ></span>

                                    <span class="min-w-0 flex-1">
                                        @if ($entry['href'])
                                            {{-- row-target, so a label is 24 pixels
                                                 tall and grows to 44 on a touch
                                                 screen. Without it this link was
                                                 19, which is under the WCAG 2.5.8
                                                 minimum, and on a phone it was a
                                                 target a thumb had to hit
                                                 accurately in a column of similar
                                                 looking lines. The plain span
                                                 below keeps the same type so a line
                                                 with no destination still lines up
                                                 with one that has. The negative
                                                 margin lets the larger hit area
                                                 grow without moving the text. --}}
                                            <a
                                                href="{{ $entry['href'] }}"
                                                class="row-target -mx-2 flex items-center rounded-sm px-2 text-sm font-medium text-ink hover:text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                            >
                                                {{ $entry['label'] }}
                                            </a>
                                        @else
                                            <span class="text-sm font-medium text-ink">{{ $entry['label'] }}</span>
                                        @endif

                                        @if (filled($entry['detail']))
                                            <span class="block text-xs text-ink-subtle">{{ $entry['detail'] }}</span>
                                        @endif
                                    </span>

                                    <time class="shrink-0 text-xs tabular-nums text-ink-subtle" datetime="{{ $entry['at']->toIso8601String() }}">
                                        {{ $entry['at']->format('g:i A') }}
                                    </time>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
