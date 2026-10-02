{{--
    The administrator report, rebuilt around shape instead of numbers in a row.

    WHAT CHANGED AND WHY

    A row of figures is cheap because it is what a table of numbers looks like. The
    report now answers two questions in the shape that fits them:

      the funnel    where learners stop. Five steps, each one narrower, with the
                    drop between them named. Five tiles answer "how many" and lose
                    "how many of the ones above", which is the question worth asking.
      the coverage  what is waiting for a person. Everything else on this page
                    describes the past; this describes a queue.

    Both are counted from columns that already existed. Nothing here is derived
    from a request, a cookie, or a third party, and the enrolment table below is
    still the row-level evidence for every figure on the page.

    MOBILE FIRST

    Every grid starts at one column and widens. The funnel is five rows on a phone
    because a five-column shape at 390px is five unreadable slivers, and the
    coverage figures are a two-up grid because one-up makes the reader scroll past
    the answer before reaching the next number.

    On a wide screen the two cards sit side by side rather than stacking, because a
    report that has to be scrolled to compare two things is a report nobody
    compares.
--}}
@extends('layouts.app-shell')

@section('title', 'Reports')
@section('workspace-context', 'Operations workspace')

@section('content')
@section('measure', 'wide')

    <x-page-header
        eyebrow="ADMINISTRATION"
        title="Enrollment report"
        description="Every row is read from the database. Amounts come from the stored payment record, never from a request."
        level="1"
    >
        <x-slot:actions>
            <x-btn :href="route('administrator.dashboard')" variant="secondary" size="md">
                <x-icon name="arrow-left" size="sm" />
                Back to dashboard
            </x-btn>
        </x-slot:actions>
    </x-page-header>

    {{--
         | The four figures, shaped here rather than in the markup below.
         |
         | A tile needs a label that says exactly what is counted, so the label and
         | the number are written together here. Splitting them across the loop and
         | the data is how a tile ends up saying "Total" over a number that counts
         | something else.
         --}}
    @php
        $reportFigures = [
            ['label' => 'Published courses', 'value' => $totals['courses'], 'hint' => 'Live in the catalog', 'tone' => 'primary'],
            ['label' => 'Enrollments', 'value' => $totals['enrollments'], 'hint' => $totals['active_enrollments'].' in progress', 'tone' => 'primary'],
            ['label' => 'Certificates issued', 'value' => $totals['certificates'], 'hint' => 'Issued and valid', 'tone' => 'success'],
            ['label' => 'Paid payments', 'value' => $totals['paid_payments'], 'hint' => 'Confirmed by the provider', 'tone' => 'primary'],
        ];
    @endphp
    {{-- ------------------------------------------------------------------ totals
         Four figures, one up on a phone, four across on a wide screen. The label
         says exactly what is counted, because a number without one is a number
         nobody can trust. --}}
    <section class="mt-8" aria-labelledby="report-totals-heading">
        <h2 id="report-totals-heading" class="sr-only">Totals for this report</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($reportFigures as $figure)
                <x-stat
                    :label="$figure['label']"
                    :value="$figure['value']"
                    :hint="$figure['hint'] ?? null"
                    :tone="$figure['tone'] ?? 'primary'"
                    :href="$figure['href'] ?? null"
                    class="h-full"
                />
            @endforeach
        </div>
    </section>
    {{-- The two cards below take the full width of the page.

         Neither of them is a two column layout at any size, and that is a
         decision rather than an omission. The funnel is read by comparing the
         length of one bar with the bar above it, and the coverage figures are a
         two by two block read as a set. Both want the whole row. --}}
         second, because it is the only part that asks something of the reader. --}}
    {{-- The funnel takes the full width of the page.

         A funnel is read by comparing the length of one bar with the bar above
         it. In a two column grid on a laptop the card is under three hundred
         pixels, and five rows of that width are five numbers with a decoration
         beside them rather than a shape. It is the primary figure on this page,
         so it gets the whole row.

         The other two charts sit side by side below it, where being narrow costs
         nothing because they are read one row at a time. --}}
    <div class="mt-8">

        {{-- ------------------------------------------------------------- the funnel --}}
        <x-card-labelled heading="Where learners stop" id="report-funnel">
            <x-slot:description>
                Five steps from enrolled to certified. Each bar is a share of everybody
                enrolled, so the shape is comparable and the drop is visible.
            </x-slot:description>

            @php
                /*
                 | The scale is the first step, not the largest value.
                 |
                 | A funnel is read by comparing each bar to the one above it, so the
                 | only denominator that means anything is everybody at the top. Scaling
                 | to the largest value would make the widest bar full width wherever it
                 | landed, which hides the one comparison the chart exists to make.
                 */
                $top = max(1, $funnel[0]['value'] ?? 1);
            @endphp

            <ol class="grid gap-4">
                @foreach ($funnel as $index => $step)
                    @php
                        $share = (int) round(($step['value'] / $top) * 100);
                        /*
                         | Five point steps, exactly as `bar-row` and the <progress>
                         | element already use them.
                         |
                         | The width is a generated class and never a `style`
                         | attribute. The content security policy is
                         | `style-src 'self'`, which forbids the style attribute: an
                         | inline width is discarded, the fill falls back to its
                         | natural width, and every bar draws full width whatever
                         | its value. This was written with an inline style first
                         | and every funnel bar was invisible for exactly that
                         | reason, in the same way the cover preview once was.
                         |
                         | `progress-step-*` sets a custom property and `.bar-fill`
                         | reads it, which is the arrangement the rest of this
                         | system uses, so a funnel bar and a chart bar cannot
                         | drift apart.
                         */
                        $stepClass = 'progress-step-'.((int) (round($share / 5) * 5));
                        $previous = $index > 0 ? $funnel[$index - 1]['value'] : null;
                        $dropped = $previous !== null ? $previous - $step['value'] : 0;
                    @endphp

                    <li>
                        {{-- The number and its share sit on one line so the row reads
                             as one fact rather than as a label and a figure that happen
                             to be near each other. --}}
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <span class="text-sm font-medium text-ink">{{ $step['label'] }}</span>
                            <span class="text-sm tabular-nums text-ink-muted">
                                <span class="text-base font-semibold text-ink">{{ $step['value'] }}</span>
                                <span class="ml-1">{{ $share }}%</span>
                            </span>
                        </div>
                        {{-- `role="presentation"`, not `role="img"`. The value and
                             the share are written out immediately above this bar, so
                             announcing the bar as well would read the same fact twice
                             with nothing added. --}}
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-surface-sunken"
                            role="presentation"
                            aria-hidden="true"
                        >
                            <div class="bar-fill {{ $stepClass }} h-full rounded-full bg-primary"></div>
                        </div>

                        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                            <span class="text-ink-subtle">{{ $step['hint'] }}</span>

                            @if ($index > 0 && $dropped > 0)
                                <span class="text-ink-subtle" aria-hidden="true">&middot;</span>
                                <span class="text-ink-subtle">{{ $dropped }} did not reach this step</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card-labelled>

        {{-- ------------------------------------------------------------- the queue --}}
        <x-card-labelled heading="Work waiting to be checked" id="report-coverage">
            <x-slot:description>
                What has been handed in, and what has not been looked at yet. These
                three numbers add up to every submission on the system.
            </x-slot:description>

            {{-- Two-up on a phone. One-up makes the reader scroll past the first
                 answer before reaching the second, and these are read as a set. --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-line bg-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Waiting</p>
                    <p class="mt-2 text-3xl font-[650] tabular-nums text-ink">{{ $coverage['awaiting'] }}</p>
                    <p class="mt-1 text-xs leading-5 text-ink-muted">Submitted, not yet checked</p>
                </div>

                <div class="rounded-lg border border-line bg-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Checked</p>
                    <p class="mt-2 text-3xl font-[650] tabular-nums text-ink">{{ $coverage['graded'] }}</p>
                    <p class="mt-1 text-xs leading-5 text-ink-muted">Marked and returned with a note</p>
                </div>

                <div class="rounded-lg border border-line bg-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Handed back</p>
                    <p class="mt-2 text-3xl font-[650] tabular-nums text-ink">{{ $coverage['returned'] }}</p>
                    <p class="mt-1 text-xs leading-5 text-ink-muted">Asked to be redone</p>
                </div>

                <div class="rounded-lg border border-line bg-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-subtle">Mean mark</p>
                    <p class="mt-2 text-3xl font-[650] tabular-nums text-ink">
                        @if ($coverage['marks'] === null)
                            <span class="text-ink-subtle">&mdash;</span>
                        @else
                            {{ (int) round($coverage['marks'] * 100) }}%
                        @endif
                    </p>
                    <p class="mt-1 text-xs leading-5 text-ink-muted">
                        @if ($coverage['marks'] === null)
                            Nothing has been marked yet
                        @else
                            Each mark as a share of its own brief
                        @endif
                    </p>
                </div>
            </div>

            <x-note tone="neutral" class="mt-4">
                {{ $coverage['briefs'] }} {{ Str::plural('brief', $coverage['briefs']) }} set,
                {{ $coverage['published'] }} open for submissions. A brief with no mark scale yet
                cannot be marked, and the interface says so on the brief itself.
            </x-note>
        </x-card-labelled>
    </div>

    {{-- ------------------------------------------------------------ the distribution
         Two charts rather than two tables. Both answer "how is the work spread",
         which is a question about shape, and a table of the same numbers is a worse
         way to see it. --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">

        <x-bar-chart
            heading="Enrollments by state"
            description="Every enrollment on the system, grouped by where it has reached."
            :rows="$stateChart"
            empty="No enrollments yet."
        />

        {{-- The empty message says "no enrollments" rather than "not enough data",
             because that is what it means here and nothing else. A chart with one
             course and no learners in it is not a chart with too little data; it is
             a chart saying nobody has signed up. --}}
        <x-bar-chart
            heading="Progress by course"
            description="Mean of what each enrolled learner has finished, against a fixed 0 to 100 scale."
            :rows="$progressChart"
            empty="No enrollments yet. A bar appears as soon as a learner enrolls in a published course."
        />

        {{-- Courses with nobody in them, named.

             The chart above collapses to an empty state when every bar is zero,
             which is right for a chart and wrong for this page: a published course
             nobody has enrolled in is precisely what an administrator is looking
             for, and dropping it left the course invisible on the whole report. --}}
        @if ($emptyCourses !== [])
            <x-note tone="warning" class="mt-3">
                No enrollments yet:
                <span class="font-medium text-ink">{{ implode(', ', $emptyCourses) }}</span>.
                A bar appears for each as soon as a learner enrolls.
            </x-note>
        @endif
    </div>

    {{-- ------------------------------------------------------------------- rows
         The row-level evidence for every figure above. It stays a table because a
         table is what a person uses when they want one specific row rather than a
         shape, and it scrolls inside its own container so a wide report does not
         widen the page. --}}
    <section class="mt-8" aria-labelledby="report-rows-heading">
        <h2 id="report-rows-heading" class="text-xl font-semibold text-ink">Enrollments</h2>

        @if ($rows->isEmpty())
            <div class="card mt-4">
                <x-empty-state
                    icon="chart"
                    title="No enrollments yet"
                    description="A row appears here as soon as a student enrolls in a course."
                >
                    <x-btn :href="route('administrator.dashboard')" variant="primary" size="md">
                        Back to dashboard
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            <div class="card mt-4 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-3xl text-left text-sm">
                        <caption class="sr-only">
                            One row per enrollment, with the student, the course, the enrollment state, the
                            number of passed quizzes, and the amount paid
                        </caption>
                        <thead class="table-head">
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Course</th>
                                <th scope="col">State</th>
                                <th scope="col">Quizzes passed</th>
                                <th scope="col">Amount paid</th>
                            </tr>
                        </thead>
                        <tbody role="list" class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="table-cell font-medium text-ink">
                                        {{ $row['student']?->name ?? 'Unknown' }}
                                    </td>
                                    <td class="table-cell">{{ $row['course']?->title ?? 'Unknown' }}</td>
                                    <td class="table-cell">
                                        <x-status :value="$row['status']->value" />
                                    </td>
                                    <td class="table-cell tabular-nums text-ink-muted">{{ $row['passed_attempts'] }}</td>
                                    <td class="table-cell">
                                        @if ($row['payment'] !== null && $row['payment']->status->value === 'paid')
                                            <x-amount
                                                :minor="$row['payment']->amount_minor"
                                                :currency="$row['payment']->currency"
                                                class="font-semibold"
                                            />
                                        @else
                                            <span class="text-ink-muted">Not paid</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="mt-4 text-sm text-ink-muted">
                Showing the {{ $rows->count() }} most recent {{ Str::plural('enrollment', $rows->count()) }}.
            </p>
        @endif
    </section>

@endsection
