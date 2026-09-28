<?php

namespace Tests\Feature;

use App\Actions\Courses\Curriculum\CreateModule;
use App\Actions\Learning\MarkLessonComplete;
use App\Actions\Learning\RecordLessonActivity;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Support\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What happens when the same thing arrives twice.
 *
 * A student double clicks a button. A browser retries a request that already
 * succeeded. Two tabs send the same form. A proxy replays a delivery. None of
 * these are exotic, and every one of them reaches the application as two
 * ordinary requests that the database is expected to survive.
 *
 * The rule these hold to is that the second request converges on the same state
 * as the first. It may be refused, and that is acceptable, but it may never
 * leave two rows where there should be one, never overwrite a newer value with
 * an older one, and never answer with a server error for an action that in fact
 * succeeded.
 *
 * A note on what this can and cannot show. These run as one process against one
 * connection, so they demonstrate the behaviour of the second request, not the
 * interleaving of two connections part way through a statement. What they do
 * check is that the code takes a row lock wherever the outcome depends on one,
 * because that lock is what makes the interleaving safe and its absence is
 * invisible to every other kind of test.
 */
class DuplicateRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course with one module and one lesson, a student enrolled in
     * it, and the instructor who owns it.
     *
     * @return array{0: User, 1: Enrollment, 2: Lesson, 3: Course, 4: User}
     */
    private function courseWithLesson(): array
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'position' => 1,
            // The policy guarding these actions only lets a student reach a
            // lesson whose own content and whose module are both published, so a
            // draft fixture is refused at the gate long before the behaviour
            // under test is ever reached.
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $enrollment, $lesson, $course, $instructor];
    }

    public function test_completing_a_lesson_twice_keeps_one_row(): void
    {
        [$student, $enrollment, $lesson] = $this->courseWithLesson();

        $action = $this->app->make(MarkLessonComplete::class);

        $first = $action->handle($student, $enrollment, $lesson);
        $second = $action->handle($student, $enrollment, $lesson);

        // One row, not two. The unique (enrollment_id, lesson_id) constraint is
        // the last line of defence; the single statement is what keeps a repeat
        // request from ever reaching it and turning into a server error.
        $this->assertSame(1, LessonProgress::query()->count());
        $this->assertTrue($first->is($second));
    }

    public function test_completing_a_lesson_twice_keeps_the_first_completion_time(): void
    {
        [$student, $enrollment, $lesson] = $this->courseWithLesson();

        $action = $this->app->make(MarkLessonComplete::class);

        $original = $action->handle($student, $enrollment, $lesson)->completed_at;

        // A student who reopens a finished lesson and presses the control again
        // has not finished it twice. Moving the time would quietly change the
        // moment they are recorded as having completed it.
        $this->travel(3)->hours();

        $again = $action->handle($student, $enrollment, $lesson);

        $this->assertTrue(
            $original->equalTo($again->completed_at),
            'A repeat completion moved the recorded completion time.'
        );
    }

    public function test_opening_a_lesson_never_undoes_a_completion(): void
    {
        [$student, $enrollment, $lesson] = $this->courseWithLesson();

        $this->app->make(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);

        // This is the fault that mattered. Recording a visit used to read the
        // row, change last_viewed_at in memory and save the whole model, so a
        // page load in a second tab wrote the stale value back over a completion
        // that had already happened. Nothing looked wrong and a student's work
        // silently went back to unfinished.
        $this->app->make(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame(LessonProgressStatus::Completed, $progress->status);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_opening_a_lesson_three_times_keeps_one_row(): void
    {
        [$student, $enrollment, $lesson] = $this->courseWithLesson();

        $action = $this->app->make(RecordLessonActivity::class);

        $action->handle($student, $enrollment, $lesson);
        $action->handle($student, $enrollment, $lesson);
        $action->handle($student, $enrollment, $lesson);

        $this->assertSame(1, LessonProgress::query()->count());
    }

    public function test_opening_an_unstarted_lesson_starts_it(): void
    {
        [$student, $enrollment, $lesson] = $this->courseWithLesson();

        $this->app->make(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame(LessonProgressStatus::InProgress, $progress->status);
        $this->assertNotNull($progress->started_at);
    }

    public function test_adding_modules_repeatedly_never_reuses_a_position(): void
    {
        [, , , $course, $instructor] = $this->courseWithLesson();

        $action = $this->app->make(CreateModule::class);

        $positions = [];

        // Six adds in a row, which is what a double click and a retry together
        // look like from the database's point of view. The fixture already holds
        // one module at position 1, so a correct first append is 2.
        for ($i = 0; $i < 6; $i++) {
            $positions[] = $action->handle($instructor, $course, ['title' => 'Module '.$i])->position;
        }

        $this->assertSame([2, 3, 4, 5, 6, 7], $positions);
        $this->assertCount(7, $course->modules()->get());
    }

    public function test_reserving_a_position_locks_the_parent_row(): void
    {
        $course = Course::factory()->create();

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        DB::transaction(fn () => Position::reserve($course, $course->modules()));

        // The lock is the entire mechanism. Without it, reading the maximum and
        // writing the new row are two independent statements, and two requests
        // arriving together both read the same maximum and both choose the same
        // number. One of them then loses on the unique constraint and the
        // instructor is shown a server error for an action they simply repeated.
        $this->assertTrue(
            collect($statements)->contains(fn (string $sql): bool => str_contains($sql, 'for update')),
            'Reserving a position did not lock the parent row. Statements seen: '.implode(' | ', $statements)
        );
    }
}
