<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Reporting\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * What each dashboard costs, measured rather than assumed.
 *
 * A dashboard is the page every role opens first and the one most likely to be
 * opened by many people at once, so its query count is the number that decides
 * whether the application stays responsive. A count that is fine on an empty
 * database says nothing, so these tests build a realistic load first and then
 * measure.
 *
 * The important property is not the absolute number but how it moves. A report
 * that runs a fixed number of queries no matter how much data exists is bounded
 * by construction. One that grows with the number of a student's enrollments is
 * not, and that is the failure these tests exist to prevent returning.
 */
class DashboardQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private QueryCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->counter = new QueryCounter;
    }

    /**
     * One student with $enrollments enrollments, each holding $lessons lessons.
     *
     * This is the shape that makes an N+1 visible: a student who has worked
     * through several courses, which is the normal case rather than an edge.
     */
    private function studentWith(int $enrollments, int $lessons = 12): User
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        for ($c = 0; $c < $enrollments; $c++) {
            $course = Course::factory()->create([
                'instructor_id' => $instructor->id,
                'status' => CourseStatus::Published,
                'course_type' => CourseType::Free,
                'price_minor' => 0,
            ]);

            $module = Module::factory()->create([
                'course_id' => $course->id,
                'status' => ContentStatus::Published,
            ]);

            $lessonIds = [];

            for ($l = 0; $l < $lessons; $l++) {
                $lesson = Lesson::factory()->create([
                    'module_id' => $module->id,
                    // The factory always writes position 1, which collides with
                    // the unique(module_id, position) rule on the second lesson.
                    // Setting it here builds a realistically ordered module.
                    'position' => $l + 1,
                    'status' => ContentStatus::Published,
                    'is_required' => true,
                ]);

                $lessonIds[] = $lesson->id;
            }

            $enrollment = Enrollment::factory()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => EnrollmentStatus::Active,
            ]);

            // Half the lessons done, so the percentage is a real number and not
            // an accident of an empty table. The factory's default state is
            // NotStarted, so the completed state has to be asked for.
            foreach (array_slice($lessonIds, 0, (int) ($lessons / 2)) as $lessonId) {
                LessonProgress::factory()->completed()->create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $student->id,
                    'lesson_id' => $lessonId,
                ]);
            }
        }

        return $student->fresh();
    }

    public function test_the_average_progress_figure_does_not_grow_with_the_number_of_enrollments(): void
    {
        $report = $this->app->make(OperationsReport::class);

        $one = $this->studentWith(1);
        $small = $this->counter->measure(fn () => $report->averageStudentProgress($one));

        $many = $this->studentWith(6);
        $large = $this->counter->measure(fn () => $report->averageStudentProgress($many));

        // One enrollment costs a query to read it plus a fixed number to measure
        // it. Six must not cost six times as much. Without a batched read this
        // is two queries per enrollment, so six is twelve against three.
        $this->assertLessThanOrEqual(
            $small['count'] + 2,
            $large['count'],
            'averageStudentProgress issues a query per enrollment. '
                ."one enrollment: {$small['count']}, six: {$large['count']}. "
                .'Most repeated: '.QueryCounter::worst($large['queries'])
        );
    }

    public function test_the_average_progress_figure_is_still_correct_after_batching(): void
    {
        $student = $this->studentWith(3, 10);

        $report = $this->app->make(OperationsReport::class);

        // Half of ten lessons is fifty per cent, and the mean of three identical
        // courses is fifty. A batched read that returned the wrong number would
        // be a faster wrong answer, which is worse than the slow one.
        $this->assertSame(50, $report->averageStudentProgress($student));
    }

    public function test_the_administrator_enrollment_report_does_not_query_per_row(): void
    {
        $this->studentWith(4, 4);

        $report = $this->app->make(OperationsReport::class);

        $measured = $this->counter->measure(fn () => $report->enrollmentRows(50));

        // Four rows, each eager loaded. The payment lookup must be one query for
        // the page rather than one per row, so the count stays small and flat as
        // the report grows toward its fifty row limit.
        $this->assertLessThanOrEqual(
            8,
            $measured['count'],
            'enrollmentRows appears to query inside its row loop. '
                ."Ran {$measured['count']} queries for 4 rows. "
                .'Most repeated: '.QueryCounter::worst($measured['queries'])
        );
    }

    public function test_the_enrollment_report_returns_a_payment_for_each_row(): void
    {
        $this->studentWith(2, 2);

        $rows = $this->app->make(OperationsReport::class)->enrollmentRows(50);

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            // Batching the payment lookup must not drop it. A row that silently
            // loses its payment is a report that quietly stops being true.
            $this->assertArrayHasKey('payment', $row);
            $this->assertArrayHasKey('enrollment', $row);
            $this->assertArrayHasKey('passed_attempts', $row);
        }
    }

    public function test_the_administrator_counters_run_a_bounded_number_of_queries(): void
    {
        $this->studentWith(3, 3);

        $measured = $this->counter->measure(
            fn () => $this->app->make(OperationsReport::class)->forAdministrator()
        );

        // Eleven figures, each a counted query, plus a handful for eager loads
        // elsewhere. This is a fixed cost: it does not grow with the data, which
        // is what makes it safe to serve to many administrators at once.
        $this->assertLessThanOrEqual(
            15,
            $measured['count'],
            "forAdministrator ran {$measured['count']} queries. ".QueryCounter::worst($measured['queries'])
        );
    }

    public function test_the_instructor_counters_run_a_bounded_number_of_queries(): void
    {
        $this->studentWith(4, 4);

        $instructor = User::query()->whereHas('profile', fn ($q) => $q->where('role', 'instructor'))->firstOrFail();

        $measured = $this->counter->measure(
            fn () => $this->app->make(OperationsReport::class)->forInstructor($instructor)
        );

        // Four owned courses and sixteen enrollments. A query per course or per
        // enrollment would show up here as a count well above this.
        $this->assertLessThanOrEqual(
            15,
            $measured['count'],
            "forInstructor ran {$measured['count']} queries for 4 courses. ".QueryCounter::worst($measured['queries'])
        );
    }
}
