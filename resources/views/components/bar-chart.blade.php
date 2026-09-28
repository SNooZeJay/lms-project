@props([
    'heading',
    'description' => null,
    'rows' => [],
    'empty' => null,
])

{{--
    A small horizontal bar chart.

    Deliberately not a general purpose chart. Every chart on these dashboards
    answers exactly one question, and this component only draws the shape that
    answers it: a handful of numbers that share one unit, compared by length. A
    pie, a line or an area chart would need a scale and a legend to be read at
    all, and none of the three questions here needs one.

    Nothing is drawn when there is nothing to show. An empty chart is a small
    sentence, because a set of zero-length bars reads as a broken widget rather
    than as "no data yet".
--}}
@php
    // A row set where every value is zero is not a chart, it is the absence of
    // one. Drawing four full-width empty tracks would read as four full bars, so
    // the empty state is used instead. This is the common case on a new system
    // and it is exactly where a chart is most likely to look broken.
    $hasValues = collect($rows)->contains(fn (array $row): bool => (float) ($row['value'] ?? 0) > 0);
    $showChart = filled($rows) && $hasValues;

    /*
     | One scale for the whole chart.
     |
     | A row that supplies no maximum used to be scaled against its own value, so
     | every non-zero bar drew at one hundred percent. Two courses holding two
     | learners and one learner drew as two identical full bars, which is the one
     | thing a bar chart must not do: comparing lengths is the whole reason for it.
     |
     | The scale is the largest value on the chart, or the largest maximum a row
     | asked for, whichever is bigger. A row may still pin its own maximum, which is
     | how a progress chart stays on a fixed 0 to 100 scale while a count chart
     | scales to its own data.
     */
    $scale = (float) collect($rows)->max(fn (array $row): float => max(
        (float) ($row['value'] ?? 0),
        (float) ($row['max'] ?? 0),
    ));
@endphp

{{--
    A chart card, built from the same two pieces every other card uses.

    It used to put the heading, the description and the list straight inside the
    section, which meant the heading sat one pixel from the card's border. Every
    other card on the dashboard puts the same heading twenty one pixels in and
    seventeen pixels down, so two cards side by side had their titles on
    different lines at different distances from their own edges. Measured, not
    judged by eye: 1px against 21px.

    Using card-header and card-body makes the alignment structural rather than a
    coincidence of padding somebody remembered to add.
--}}
<section {{ $attributes->merge(['class' => 'card']) }} aria-labelledby="{{ $id = 'chart-'.substr(md5($heading), 0, 8) }}">
    <div class="card-header">
        <div class="min-w-0">
            <h2 id="{{ $id }}" class="text-base font-semibold text-ink">{{ $heading }}</h2>

            @if ($description)
                <p class="mt-1 text-sm leading-6 text-ink-muted">{{ $description }}</p>
            @endif
        </div>
    </div>

    <div class="card-body">
        @if ($showChart)
            <ul role="list" class="grid gap-4">
                @foreach ($rows as $row)
                    <x-bar-row
                        :label="$row['label']"
                        :value="$row['value']"
                        :max="$row['max'] ?? $scale"
                        :hint="$row['hint'] ?? null"
                        :href="$row['href'] ?? null"
                        :tone="$row['tone'] ?? 'primary'"
                    />
                @endforeach
            </ul>
        @else
            <p class="text-sm leading-6 text-ink-muted">
                {{ $empty ?? 'There isn’t enough activity to chart yet.' }}
            </p>
        @endif
    </div>
</section>
