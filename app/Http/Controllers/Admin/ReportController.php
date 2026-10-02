<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Reporting\OperationsReport;
use App\Support\StatusLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The administrator report.
 *
 * Everything the page shows is read here rather than handed over by the dashboard,
 * so the report is a complete page on its own and does not depend on another page
 * having been rendered first.
 *
 * The four new figures are computed here instead of inside the view. A count
 * inside Blade is a query inside a template, which cannot be seen, cannot be
 * cached and cannot be tested, so all four are named here and passed in.
 */
class ReportController extends Controller
{
    public function __construct(private readonly OperationsReport $report) {}

    public function index(Request $request): View
    {
        $totals = $this->report->reportTotals();
        $funnel = $this->report->completionFunnel();

        /*
         | The course rows, read ONCE.
         *
         | Three things on this page need them: the progress chart, the list of
         | courses nobody has enrolled in, and nothing else. The first version called
         | `courseProgressRows()` once per consumer, which cost eight extra queries
         | and is precisely what the query budget in the test suite exists to catch.
         */
        $courseProgress = $this->report->courseProgressRows();
        $coverage = $this->report->assessmentCoverage();

        return view('admin.reports.index', [
            'rows' => $this->report->enrollmentRows(),

            /*
             | The counts the report is built from, and the course-level progress
             | `plan.md` approves for V1. Both are read here rather than handed over
             | by the dashboard, for the reason in the class docblock.
             *
             | `forAdministrator` used to be passed as `stats` as well. The view never
             | rendered it, so eleven count queries were paid for on every visit of
             | this report and then discarded. It is gone, and the report's query cost
             | is pinned by a test so it cannot return as an unused variable.
             */
            'totals' => $totals,
            'courseProgress' => $courseProgress,

            'funnel' => $funnel,
            'coverage' => $coverage,

            /*
             | The two charts, shaped here rather than in the template.
             |
             | `bar-chart` takes rows of label, value and optional maximum, and decides
             | its own scale. Both of these pin the maximum they need to be read
             | against: the state chart is a share of all enrollments, and the progress
             | chart is a fixed 0 to 100 scale, because a progress bar that rescales to
             | its own course is a bar chart about the bar chart.
             */
            'stateChart' => $this->stateChart(),
            'progressChart' => $this->progressChart($courseProgress),

            /*
             | The published courses nobody has enrolled in, named.
             *
             | The progress chart collapses to an empty state when every value is
             | zero, which is correct for a chart but leaves those courses invisible on
             | the whole page. A course with no learners is exactly what an
             | administrator needs to see, so they are named here and printed under the
             | chart rather than being quietly dropped.
             */
            'emptyCourses' => $this->emptyCourses($courseProgress),
        ]);
    }

    /**
     * Enrollments by state, as a share of all enrollments.
     *
     * Every state is drawn, including the ones holding nothing, because the point
     * of the chart is to show that a state exists and is empty. Dropping them would
     * make a course with nobody awaiting payment look the same as one where that
     * stage does not exist.
     *
     * @return list<array{label: string, value: int, max: int, tone: string, hint: string}>
     */
    private function stateChart(): array
    {
        $counts = $this->report->enrollmentStatusCounts();
        $all = max(1, array_sum($counts));

        $rows = [];

        foreach ($counts as $status => $count) {
            $reading = StatusLabel::for((string) $status);

            $rows[] = [
                'label' => $reading['label'],
                'value' => (int) $count,
                'max' => $all,
                'tone' => $count > 0 ? 'primary' : 'muted',
                'hint' => $count.' of '.$all,
            ];
        }

        return $rows;
    }

    /**
     * Mean progress per course, on a fixed scale.
     *
     * A course with nobody enrolled IS drawn, at zero, with the reason written
     * out beside it.
     *
     * The first version left those courses out, on the reasoning that a zero bar
     * says every learner in the course has failed when in fact nobody is enrolled
     * in it. That was right about the bar and wrong about the omission: a
     * published course nobody has enrolled in is exactly what an administrator
     * needs to see, and dropping it made the course invisible on the whole page.
     *
     * So the row stays and the zero is explained. The number is the truth, and
     * the hint is what stops it being read as a failure.
     *
     * @return list<array{label: string, value: int, max: int, hint: string}>
     */
    private function progressChart(Collection $courseProgress): array
    {
        $rows = [];

        foreach ($courseProgress as $row) {
            $enrolled = (int) $row['enrolled'];

            $rows[] = [
                'label' => $row['course']->title,
                // Zero for a course nobody has enrolled in, which is a different
                // fact from a course everybody enrolled in and failed.
                'value' => $enrolled === 0 ? 0 : (int) $row['average'],
                // Pinned, so two courses can be compared by length.
                'max' => 100,
                'hint' => $enrolled === 0
                    ? 'No enrollments'
                    : $row['average'].'% average across '.$enrolled.' '.($enrolled === 1 ? 'enrollment' : 'enrollments'),
            ];
        }

        return $rows;
    }

    /**
     * Titles of published courses with nobody enrolled in them.
     *
     * Read from the same rows the progress chart is built from, so the two cannot
     * disagree about which courses are empty.
     *
     * @return list<string>
     */
    private function emptyCourses(Collection $courseProgress): array
    {
        return $courseProgress
            ->filter(fn (array $row): bool => (int) $row['enrolled'] === 0)
            ->map(fn (array $row): string => $row['course']->title)
            ->values()
            ->all();
    }
}
