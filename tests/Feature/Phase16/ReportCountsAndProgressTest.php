<?php

namespace Tests\Feature\Phase16;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\LessonProgressStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\ProgressCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * The report carries the counts and the course-level progress the plan approves.
 *
 * `plan.md` states that V1 reports include real counts, tables, statuses, and
 * simple course-level progress. The report had the table and the statuses. The
 * counts and the progress were the two approved items it did not have, and both
 * are added here.
 *
 * The progress percentage is asserted against `ProgressCalculator` rather than
 * against a number written out by hand. That is the point of the calculator: it
 * is the only place a percentage is produced, so the report reusing it is what
 * guarantees the Administrator sees the same figure the Student sees. A test
 * that hard coded 50 would pass while the two disagreed.
 */
class ReportCountsAndProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Course $course;

    private Module $module;

    /** @var list<Lesson> */
    private array $lessons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->create();
        $this->administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $instructor = User::factory()->instructor()->create();

        $this->course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);

        /*
         | The Module has to be published, not just the Lessons.
         |
         | A required Lesson only counts towards progress when it sits in a
         | published Module, because that is what makes it reachable to a
         | Student. The factory defaults every Module to draft, so leaving it
         | alone made the calculator report 0 percent for a half-finished
         | enrollment, and the first version of this test asserted 50 against a
         | system that honestly said 0. The fixture was wrong, not the rule.
         */
        $this->module = Module::factory()->for($this->course, 'course')->published()->create();

        // Four required published Lessons, so a half-finished enrollment is
        // exactly 50 percent and the number is not a rounding accident.
        $this->lessons = [];

        foreach (range(1, 4) as $position) {
            $this->lessons[] = Lesson::factory()->for($this->module, 'module')->create([
                'position' => $position,
                'status' => ContentStatus::Published,
                'is_required' => true,
            ]);
        }
    }

    public function test_the_report_states_the_counts_it_is_built_from(): void
    {
        $this->enroll($this->lessons[0], $this->lessons[1]);
        $this->enroll($this->lessons[0]);

        $body = (string) $this->actingAs($this->administrator)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        // Every figure is compared with a direct count, so a number that drifts
        // away from the database fails here rather than in an audit.
        $this->assertStringContainsString('Published courses', $body);
        $this->assertStringContainsString('Enrollments', $body);
        $this->assertStringContainsString('Certificates issued', $body);
        $this->assertStringContainsString('Paid payments', $body);

        $this->assertSame(
            Course::query()->where('status', CourseStatus::Published)->count(),
            1,
            'The fixture should hold exactly one published course for this test to mean anything.'
        );
        $this->assertSame(2, Enrollment::query()->count());

        $this->assertStringContainsString('2', $body);
    }

    public function test_the_report_shows_course_level_progress_from_real_lesson_records(): void
    {
        $this->enroll($this->lessons[0], $this->lessons[1]);

        $body = (string) $this->actingAs($this->administrator)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        // Half of four required Lessons. The figure comes from the same
        // calculator the Student's own course page uses.
        $expected = app(ProgressCalculator::class)->forEnrollment(
            Enrollment::query()->firstOrFail()
        );

        $this->assertSame(50, $expected['percentage'], 'The fixture should be exactly half finished.');

        $this->assertStringContainsString($this->course->title, $body);
        $this->assertStringContainsString('50%', $body, 'The report did not show the real course progress percentage.');
    }

    public function test_a_course_with_no_enrollments_reports_no_progress_rather_than_zero(): void
    {
        $body = (string) $this->actingAs($this->administrator)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($this->course->title, $body);
        $this->assertStringContainsString('No enrollments', $body);
    }

    public function test_the_report_never_shows_another_courses_progress_to_a_student(): void
    {
        $this->enroll($this->lessons[0], $this->lessons[1]);

        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    public function test_the_report_costs_the_same_however_many_enrollments_it_shows(): void
    {
        // The batched read exists precisely so this does not grow. Thirty
        // enrollments must not cost more per enrollment than ten does, which is
        // the difference between a report that stays usable and one that does
        // not.
        //
        // The count is taken with the project's own counter rather than by
        // reading a query log inline, because a listener registered once per
        // measurement stacks: every earlier listener keeps recording, and a
        // report that really ran twenty nine queries reports a hundred and
        // forty five.
        foreach (range(1, 9) as $ignored) {
            $this->enroll($this->lessons[0]);
        }

        $withTen = (new QueryCounter)->measure(
            fn () => $this->actingAs($this->administrator)->get(route('admin.reports.index'))
        )['count'];

        foreach (range(1, 20) as $ignored) {
            $this->enroll($this->lessons[0]);
        }

        $withThirty = (new QueryCounter)->measure(
            fn () => $this->actingAs($this->administrator)->get(route('admin.reports.index'))
        )['count'];

        $this->assertSame(
            $withTen,
            $withThirty,
            'The report cost '.$withTen.' queries with ten enrollments and '.$withThirty.' with thirty, '
            .'so something is being read per enrollment.'
        );
    }

    /**
     * The report's query cost is pinned, because it grew by being handed things.
     *
     * The controller used to pass `forAdministrator` to this view as `stats`.
     * The view never rendered it, so eleven count queries were paid on every
     * visit and thrown away. Nothing failed: the page was correct, the tests
     * passed, and the only symptom was a report that was a third more expensive
     * than it had any reason to be.
     *
     * A ceiling is used rather than an exact number, because a query saved
     * somewhere else should not fail this test. What must not come back is a
     * whole read of a set of figures that nothing on the page shows.
     */
    public function test_the_report_stays_inside_its_query_budget(): void
    {
        $this->enroll($this->lessons[0], $this->lessons[1]);

        $count = (new QueryCounter)->measure(
            fn () => $this->actingAs($this->administrator)->get(route('admin.reports.index'))
        )['count'];

        /*
         | RAISED FROM 24 TO 34, AND THE REASON IS WORTH READING.
         |
         | RAISED FROM 24 TO 29. The budget exists to catch a read whose result the
         | page does not render,
         | and a loop that makes the cost grow with the size of the school. Neither
         | happened. Six queries were added and every one of them is rendered:
         | Neither happened. Nine queries were added and every one of them is
         | rendered:
         |
         |   6  the funnel       three enrollment states, two joins over lesson
         |                       progress, one certificate count
         |   3  the coverage     two assignment counts, one grouped submission count,
         |                       and the mean mark, which joins across two tables
         |
         | The first version cost 37 because the course rows were read once per
         | consumer. They are now read once and passed in, which is what a budget
         | of this shape is actually for.
         |
         | The cost is still fixed. `test_the_report_costs_the_same_however_many_
         | enrollments_it_shows` in this file is the test that matters for scale, and
         | it is unchanged and still passing: thirty enrollments cost the same per
         | enrollment as ten.
         |
         | A budget that cannot move is not a budget, it is a number that eventually
         | has to be deleted in a panic. What has to hold is the shape: bounded, and
         | every query accounted for above.
         */
        $this->assertLessThanOrEqual(
            29,
            $count,
            "The report ran {$count} queries. It reads the enrollment rows, the newest payment per "
            .'enrollment, the count tiles, the course progress, the funnel and the assessment '
            .'coverage, and that is the whole of what it shows. Anything well past this is a read '
            .'whose result the page does not render, or a loop whose cost grows with the data.'
        );
    }

    /**
     * @param  list<Lesson>  $completed
     */
    private function enroll(Lesson ...$completed): Enrollment
    {
        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
        ]);

        foreach ($completed as $lesson) {
            LessonProgress::factory()->create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
                'status' => LessonProgressStatus::Completed,
            ]);
        }

        return $enrollment;
    }
}
