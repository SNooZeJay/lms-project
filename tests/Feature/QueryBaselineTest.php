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
 * Prints the query cost of each dashboard path, one clean measurement each.
 *
 * This is a measurement run, not a gate. It exists so the numbers behind the
 * optimisation work are visible rather than asserted, and so a later change can
 * be compared against a recorded baseline.
 *
 * Each case runs in its own test with its own database, because a shared one
 * makes every figure depend on whatever the previous case happened to leave
 * behind. A measurement that is not reproducible is a guess with a decimal
 * point.
 */
class QueryBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_progress_scales_with_enrollment_count(): void
    {
        $counter = new QueryCounter;
        $report = $this->app->make(OperationsReport::class);

        foreach ([1, 4, 12] as $count) {
            $student = $this->seedStudentWith($count, 12);

            $measured = $counter->measure(fn () => $report->averageStudentProgress($student));

            fwrite(STDERR, sprintf(
                "  averageStudentProgress  enrollments=%-3d queries=%-4d %6.1fms\n",
                $count,
                $measured['count'],
                $measured['time']
            ));
        }

        $this->assertTrue(true);
    }

    public function test_enrollment_report_scales_with_row_count(): void
    {
        $counter = new QueryCounter;
        $report = $this->app->make(OperationsReport::class);

        foreach ([5, 25, 50] as $rows) {
            $this->seedStudentWith($rows, 3);

            $measured = $counter->measure(fn () => $report->enrollmentRows(50));

            fwrite(STDERR, sprintf(
                "  enrollmentRows         rows=%-3d queries=%-4d %6.1fms   worst: %s\n",
                $rows,
                $measured['count'],
                $measured['time'],
                substr(QueryCounter::worst($measured['queries']), 0, 66)
            ));
        }

        $this->assertTrue(true);
    }

    public function test_the_counters_and_agenda_are_fixed_cost(): void
    {
        $this->seedStudentWith(20, 6);

        $counter = new QueryCounter;
        $report = $this->app->make(OperationsReport::class);

        $admin = $counter->measure(fn () => $report->forAdministrator());
        fwrite(STDERR, sprintf("  forAdministrator        queries=%-4d %6.1fms\n", $admin['count'], $admin['time']));

        $instructor = User::query()->whereHas('profile', fn ($q) => $q->where('role', 'instructor'))->firstOrFail();
        $inst = $counter->measure(fn () => $report->forInstructor($instructor));
        fwrite(STDERR, sprintf("  forInstructor           queries=%-4d %6.1fms\n", $inst['count'], $inst['time']));

        $agenda = $counter->measure(fn () => $report->administratorAgenda(6));
        fwrite(STDERR, sprintf("  administratorAgenda     queries=%-4d %6.1fms\n", $agenda['count'], $agenda['time']));

        $this->assertTrue(true);
    }

    /**
     * A student with $enrollments enrollments, each holding $lessons lessons.
     *
     * Returns the student rather than the instructor, because the newest user
     * created here is the instructor and measuring progress against an account
     * with no enrollments reports one query forever and looks perfect.
     */
    private function seedStudentWith(int $enrollments, int $lessons): User
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

            $ids = [];
            for ($l = 0; $l < $lessons; $l++) {
                $ids[] = Lesson::factory()->create([
                    'module_id' => $module->id,
                    'position' => $l + 1,
                    'status' => ContentStatus::Published,
                    'is_required' => true,
                ])->id;
            }

            $enrollment = Enrollment::factory()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => EnrollmentStatus::Active,
            ]);

            foreach (array_slice($ids, 0, (int) ($lessons / 2)) as $lessonId) {
                LessonProgress::factory()->completed()->create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $student->id,
                    'lesson_id' => $lessonId,
                ]);
            }
        }

        return $student->fresh();
    }
}
