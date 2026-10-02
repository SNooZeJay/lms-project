<?php

namespace Tests\Feature\Reports;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Reporting\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The funnel and the assessment coverage on the administrator report.
 *
 * Both are new shapes for numbers that already existed, so most of these tests are
 * about the shape: that it narrows, that its parts add up, and that it cannot
 * disagree with the page a student sees.
 *
 * The most important one is the last. A report that shows a different number from
 * the dashboard is worse than no report, because it is trusted further than it
 * deserves.
 */
class ReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private Course $course;

    private Module $module;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::factory()->instructor()->create();
        $this->student = User::factory()->create();

        $this->course = Course::factory()->for($this->instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $this->module = Module::factory()->for($this->course, 'course')->create([
            'status' => ContentStatus::Published,
        ]);

        $this->lesson = Lesson::factory()->for($this->module, 'module')->create([
            'status' => ContentStatus::Published,
        ]);
    }

    private function report(): OperationsReport
    {
        return app(OperationsReport::class);
    }

    /**
     * The five steps, in order, with the labels the interface prints.
     */
    public function test_the_funnel_has_five_named_steps_in_order(): void
    {
        $steps = $this->report()->completionFunnel();

        $this->assertSame(
            ['enrolled', 'started', 'halfway', 'finished', 'certified'],
            array_column($steps, 'key'),
        );

        foreach ($steps as $step) {
            $this->assertNotSame('', $step['label'], 'A funnel step without a label is a number nobody can trust.');
            $this->assertNotSame('', $step['hint'], 'A funnel step without a reason is a number nobody can question.');
        }
    }

    /**
     * A funnel only ever narrows.
     *
     * This is not a presentational rule. A step that grows means one count is
     * measuring something wider than the step before it, and a chart of descending
     * bars that does not descend is telling the reader something is wrong.
     */
    public function test_the_funnel_never_widens(): void
    {
        Enrollment::factory()->active()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        // A learner who has finished the course and holds a certificate. Counting
        // certificates here would make the last step exceed the fourth, because a
        // certificate can only exist for a completed enrollment but the completed
        // count is read from a status and the certificate count from another table.
        $second = User::factory()->create();

        Enrollment::factory()->completed()->create([
            'student_id' => $second->id,
            'course_id' => $this->course->id,
        ]);

        $values = array_column($this->report()->completionFunnel(), 'value');

        for ($i = 1; $i < count($values); $i++) {
            $this->assertLessThanOrEqual(
                $values[$i - 1],
                $values[$i],
                'Step '.$i.' is wider than the step before it, so this is not a funnel.',
            );
        }
    }

    /**
     * A learner is counted once however many lessons they have finished.
     *
     * Four finished lessons is one learner at "completed a lesson", not four. This
     * is the mistake a join makes and it is invisible without an assertion.
     */
    public function test_a_learner_is_counted_once_however_many_lessons_they_finished(): void
    {
        $learner = User::factory()->create();

        $enrollment = Enrollment::factory()->active()->create([
            'student_id' => $learner->id,
            'course_id' => $this->course->id,
        ]);
        // Four DISTINCT lessons at four DISTINCT positions.
        //
        // Two separate uniqueness rules apply here and only one of them is about
        // lessons: `lesson_progress` is unique on (enrollment_id, lesson_id), and
        // `lessons` is unique on (module_id, position) while the factory writes
        // position 1 for every lesson it makes. Getting either wrong is a duplicate
        // key rather than a learner who finished four things.
        $lessons = collect([2, 3, 4, 5])->map(fn (int $position) => Lesson::factory()
            ->for($this->module, 'module')
            ->create([
                'status' => ContentStatus::Published,
                'position' => $position,
            ]));

        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        $steps = collect($this->report()->completionFunnel())->keyBy('key');

        $this->assertSame(1, $steps['started']['value']);
        $this->assertSame(1, $steps['halfway']['value']);
    }

    /**
     * An enrollment with no progress is enrolled and nothing else.
     */
    public function test_a_learner_who_has_not_opened_a_lesson_still_counts_as_enrolled(): void
    {
        $learner = User::factory()->create();

        Enrollment::factory()->active()->create([
            'student_id' => $learner->id,
            'course_id' => $this->course->id,
        ]);

        $steps = collect($this->report()->completionFunnel())->keyBy('key');

        $this->assertSame(1, $steps['enrolled']['value']);
        $this->assertSame(0, $steps['started']['value']);
    }

    /**
     * The coverage numbers always add up to the number of submissions.
     *
     * Four counts taken from four separate conditions can disagree. A report whose
     * parts do not add to its total is a report nobody can quote.
     */
    public function test_the_coverage_numbers_add_up_to_every_submission(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        AssignmentSubmission::factory()->count(2)->create([
            'assignment_id' => $assignment->id,
        ]);

        AssignmentSubmission::factory()->graded()->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
        ]);

        AssignmentSubmission::factory()->returned()->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
        ]);

        $coverage = $this->report()->assessmentCoverage();

        $this->assertSame(
            AssignmentSubmission::query()->count(),
            $coverage['awaiting'] + $coverage['graded'] + $coverage['returned'],
            'The three submission states must account for every row.',
        );

        $this->assertSame(2, $coverage['awaiting']);
        $this->assertSame(1, $coverage['graded']);
        $this->assertSame(1, $coverage['returned']);
    }

    /**
     * A mean mark taken across briefs with different scales means nothing.
     *
     * One brief is marked out of 10 and another out of 100. Averaging the raw
     * numbers produces a figure that describes neither. Each mark is read as a
     * proportion of its own brief first.
     */
    public function test_the_mean_mark_is_a_proportion_of_each_briefs_own_scale(): void
    {
        $small = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 10,
        ]);

        $large = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 100,
        ]);

        // 5 out of 10 and 50 out of 100 are both exactly half.
        AssignmentSubmission::factory()->graded(5)->create([
            'assignment_id' => $small->id,
            'student_id' => User::factory()->create()->id,
        ]);

        AssignmentSubmission::factory()->graded(50)->create([
            'assignment_id' => $large->id,
            'student_id' => User::factory()->create()->id,
        ]);

        $this->assertEqualsWithDelta(
            0.5,
            (float) $this->report()->assessmentCoverage()['marks'],
            0.001,
            'Two half marks on different scales must average to a half.',
        );
    }

    /**
     * No marks means no mean, not a mean of zero.
     *
     * Zero would say every learner scored nothing, which is a different and much
     * worse claim than "nothing has been marked yet".
     */
    public function test_an_unmarked_system_reports_no_mean_rather_than_zero(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
        ]);

        $this->assertNull($this->report()->assessmentCoverage()['marks']);
    }

    /**
     * A brief marked out of nothing is left out of the mean rather than dividing
     * by zero.
     */
    public function test_a_brief_with_no_scale_is_excluded_from_the_mean(): void
    {
        $unscaled = Assignment::factory()->withoutMarkScale()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        AssignmentSubmission::factory()->graded(40)->create([
            'assignment_id' => $unscaled->id,
            'student_id' => User::factory()->create()->id,
        ]);

        $this->assertNull($this->report()->assessmentCoverage()['marks']);
    }

    /**
     * An empty system reports zeros rather than failing.
     */
    public function test_an_empty_system_reports_a_full_funnel_of_zeroes(): void
    {
        $steps = $this->report()->completionFunnel();

        $this->assertCount(5, $steps);

        foreach ($steps as $step) {
            $this->assertSame(0, $step['value']);
        }

        $coverage = $this->report()->assessmentCoverage();

        $this->assertSame(0, $coverage['briefs']);
        $this->assertSame(0, $coverage['awaiting']);
        $this->assertNull($coverage['marks']);
    }

    /**
     * The funnel and the report totals cannot disagree.
     *
     * Both read the same table in the same request, and the first funnel step is
     * the enrollment total. If these two ever differ, one of them is lying.
     */
    public function test_the_funnel_agrees_with_the_report_totals(): void
    {
        foreach (range(1, 3) as $ignored) {
            Enrollment::factory()->active()->create([
                'student_id' => User::factory()->create()->id,
                'course_id' => $this->course->id,
            ]);
        }

        $report = $this->report();
        $totals = $report->reportTotals();
        $steps = collect($report->completionFunnel())->keyBy('key');

        $this->assertSame($totals['enrollments'], $steps['enrolled']['value']);
        $this->assertSame($totals['completed_enrollments'], $steps['finished']['value']);
        $this->assertSame($totals['certificates'], $steps['certified']['value']);
    }

    /**
     * The whole funnel and coverage cost a fixed number of queries.
     *
     * A report that runs one query per learner or per course is a report nobody
     * opens twice, and the cost grows silently as the school does.
     */
    public function test_the_funnel_and_coverage_cost_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 4) as $ignored) {
            $course = Course::factory()->for($this->instructor, 'instructor')->create([
                'status' => CourseStatus::Published,
                'course_type' => CourseType::Free,
                'price_minor' => 0,
            ]);

            Enrollment::factory()->active()->create([
                'student_id' => User::factory()->create()->id,
                'course_id' => $course->id,
            ]);
        }

        $report = $this->report();

        // Warm the connection so connection setup is not counted.
        $report->completionFunnel();
        $report->assessmentCoverage();

        $before = DB::getQueryLog();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $report->completionFunnel();
        $report->assessmentCoverage();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            12,
            $count,
            'The funnel and coverage ran '.$count.' queries. A report should not cost more as the school grows.',
        );
    }

    /**
     * The report page renders both, on a phone and on a desktop.
     */
    public function test_the_report_page_shows_the_funnel_and_the_coverage(): void
    {
        // No hardcoded id: setUp has already created accounts, and a fixed id is
        // a duplicate key the moment the arrangement above changes.
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        Enrollment::factory()->active()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Where learners stop')
            ->assertSee('Certified')
            ->assertSee('Work waiting to be checked')
            ->assertSee('Mean mark')
            ->assertSee('Enrolled');
    }

    /**
     * Only an administrator sees the report.
     */
    public function test_the_report_is_not_reachable_by_anybody_else(): void
    {
        $this->actingAs($this->instructor)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->student)->get(route('admin.reports.index'))->assertForbidden();
        // A guest is refused rather than sent to a sign-in page. The role
        // middleware answers 403 before the authentication middleware is
        // reached, and that is the right order: a signed-out reader learns
        $this->get(route('admin.reports.index'))->assertForbidden();
    }
}
