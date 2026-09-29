@extends('layouts.app-shell')

@section('title', 'Reports')
@section('workspace-context', 'Operations workspace')

@php
    /*
     | Four tiles, and the fifth figure is not dropped.
     |
     | A tile strip reads as a grid, and a grid with a gap in its last row reads
     | as something failed to load. Five do not divide across the widths this
     | project already uses, so the completed count is left out of the strip: it
     | is the Completed column of the table directly below, which is a better
     | place for it anyway, since that table is the thing it is a total of.
     |
     | The Administrator dashboard made the same call for the same reason, folding
     | eight figures down to six rather than leaving a ragged row.
     */
    $reportFigures = [
        ['label' => 'Published courses', 'value' => $totals['courses'], 'hint' => 'Live in the catalog', 'tone' => 'primary'],
        ['label' => 'Enrollments', 'value' => $totals['enrollments'], 'hint' => $totals['active_enrollments'].' in progress', 'tone' => 'primary'],
        ['label' => 'Certificates issued', 'value' => $totals['certificates'], 'hint' => 'Issued and valid', 'tone' => 'accent'],
        ['label' => 'Paid payments', 'value' => $totals['paid_payments'], 'hint' => 'Confirmed by the provider', 'tone' => 'accent'],
    ];
@endphp

@section('content')
@section('measure', 'wide')
            <x-page-header
            eyebrow="Administration"
            title="Enrollment report"
            description="Every row is read from the database. Amounts come from the stored payment record, never from a request."
        >
            <x-slot:actions>
                <x-btn :href="route('administrator.dashboard')" variant="secondary" size="md">
                    <x-icon name="arrow-left" size="sm" />
                    Back to dashboard
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mt-6" />

        {{--
            The counts the tables below are drawn from, stated before the tables
            rather than after. A reader who wants to know what they are looking at
            should not have to count the rows to find out, and the two views can
            then be compared against each other by eye.

            Every figure is a count over stored records. Nothing here is estimated,
            and nothing is a sample: `plan.md` rules out fabricated metrics for this
            release, so there is no figure on this page that is not a `count()`.
        --}}
        <section class="mt-8" aria-labelledby="report-totals-heading">
            <h2 id="report-totals-heading" class="sr-only">Totals for this report</h2>

            <dl role="list" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($reportFigures as $figure)
                    <x-stat
                        :label="$figure['label']"
                        :value="$figure['value']"
                        :hint="$figure['hint']"
                        :tone="$figure['tone']"
                    />
                @endforeach
            </dl>
        </section>

        {{--
            Course-level progress, which `plan.md` approves for V1 as "simple
            course-level progress".

            The percentage in each row is the mean of the per-enrollment figures
            the Students themselves see, produced by the one calculator that makes
            them, so a number here and a number on a course page cannot disagree.

            A course with nobody enrolled says so rather than showing 0 percent.
            A zero is read as a failure, and nobody failed anything in a course
            that was never opened.
        --}}
        <section class="mt-8" aria-labelledby="report-course-progress-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="report-course-progress-heading" class="text-xl font-semibold text-ink">
                    Progress by course
                </h2>
                <p class="text-sm text-ink-muted">
                    Mean of what each enrolled learner has finished.
                </p>
            </div>

            {{--
                Progress and the Finished column measure different things, and a
                row can legitimately read 100 percent with nothing finished.

                Progress is lessons. Finishing an enrollment also takes the
                assessments, so a learner who has read every lesson and has not
                passed the quiz is at 100 percent and not finished. Both figures
                are correct and showing one without the other would be the
                confusing part, so the difference is stated rather than left for
                a reader to guess at.
            --}}
            <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-muted">
                Progress counts lessons. A learner is only counted as finished once the course is
                complete, which also takes the assessments, so a course can read 100 percent with
                nobody finished yet.
            </p>

            @if ($courseProgress->isEmpty())
                <div class="card mt-4">
                    <x-empty-state
                        icon="chart"
                        title="No published courses yet"
                        description="A row appears here as soon as a course is published."
                    >
                        <x-btn :href="route('admin.reports.index')" variant="secondary" size="md">
                            Refresh
                        </x-btn>
                    </x-empty-state>
                </div>
            @else
                <div class="card mt-4 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-3xl text-left text-sm">
                            <caption class="sr-only">
                                One row per published course, with how many learners are enrolled, how many
                                have finished, and the mean completion across them
                            </caption>
                            <thead class="table-head">
                                <tr>
                                    <th scope="col">Course</th>
                                    <th scope="col" class="text-right">Enrolled</th>
                                    <th scope="col" class="text-right">Finished</th>
                                    <th scope="col">Mean progress</th>
                                </tr>
                            </thead>
                            <tbody role="list" class="divide-y divide-line">
                                @foreach ($courseProgress as $row)
                                    <tr>
                                        <th scope="row" class="table-cell font-medium text-ink">
                                            {{ $row['course']->title }}
                                        </th>
                                        <td class="table-cell text-right tabular-nums text-ink-muted">
                                            {{ $row['enrolled'] }}
                                        </td>
                                        <td class="table-cell text-right tabular-nums text-ink-muted">
                                            {{ $row['completed'] }}
                                        </td>
                                        <td class="table-cell">
                                            @if ($row['enrolled'] === 0)
                                                <span class="text-sm text-ink-muted">No enrollments</span>
                                            @elseif ($row['counted'] === 0)
                                                {{--
                                                    Enrolled, but no required published Lessons, so there is
                                                    nothing to have finished. Saying so is honest; a zero
                                                    percent bar would read as a cohort that failed.
                                                --}}
                                                <span class="text-sm text-ink-muted">Nothing required yet</span>
                                            @else
                                                <x-progress
                                                    :percentage="$row['average']"
                                                    label="Mean progress"
                                                    :detail="$row['average'].'% average across '.$row['counted'].' '.Str::plural('enrollment', $row['counted'])"
                                                    size="sm"
                                                />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

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
                {{-- The table scrolls inside its own container rather than
                     widening the page, which is the behaviour the design system
                     requires for a wide administrator report. --}}
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
