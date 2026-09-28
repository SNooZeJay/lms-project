<?php

namespace Tests\Feature\Phase16;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Reporting\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Student activity feed has to contain the thing a learner actually does.
 *
 * `OperationsReport::studentAgenda` merges five sources: enrollments, course
 * completions, certificates, quiz attempts and payments. Its own docblock
 * promises six, listing "a lesson finished" among them, and there was no lesson
 * source in the code.
 *
 * That is not a documentation slip, it is a hole in the page. A learner who
 * opens the LMS most days and finishes a lesson a day produces one new event,
 * and that event was not one the feed knew about. Everything the feed did know
 * about happens once, on day one, so the feed went quiet exactly when the
 * learner was most active.
 *
 * It is also self-contradicting on the page. The Student dashboard reported 21
 * lessons completed and, two panels away, "Nothing has happened on your account
 * yet."
 *
 * These tests pin the source and the two properties that matter about it: it
 * is the learner's own lessons only, and it stays in newest-first order among
 * the other sources rather than being appended to the end.
 */
class StudentActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Module $module;

    /** @var list<Lesson> */
    private array $lessons;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();

        $instructor = User::factory()->instructor()->create();

        $this->course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);

        $this->module = Module::factory()->for($this->course, 'course')->published()->create();

        $this->lessons = [];

        foreach (range(1, 3) as $position) {
            $this->lessons[] = Lesson::factory()->for($this->module, 'module')->create([
                'position' => $position,
                'title' => 'Lesson number '.$position,
                'status' => ContentStatus::Published,
                'is_required' => true,
            ]);
        }

        // Built through the factory state rather than by overriding `status`, so
        // `activated_at` is set the way the real enrollment flow sets it. Doing
        // it the other way is what produced a live enrollment the application
        // can never create, and it is why the feed looked empty.
        $this->enrollment = Enrollment::factory()->active()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_finishing_a_lesson_appears_in_the_feed(): void
    {
        $this->completeLesson($this->lessons[0]);

        $labels = $this->feedLabels();

        $this->assertContains(
            'Finished Lesson number 1',
            $labels,
            'A learner who finished a lesson produced an event the feed did not have. The dashboard '
            .'showed the lesson count and then said nothing had happened.'
        );
    }

    /** A lesson merely opened is not a finished lesson, and must not be claimed as one. */
    public function test_a_started_but_unfinished_lesson_is_not_reported_as_finished(): void
    {
        LessonProgress::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'lesson_id' => $this->lessons[0]->id,
            'status' => LessonProgressStatus::InProgress,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $this->assertNotContains('Finished Lesson number 1', $this->feedLabels());
    }

    public function test_another_students_lesson_never_appears(): void
    {
        $other = User::factory()->create();

        $otherEnrollment = Enrollment::factory()->active()->create([
            'student_id' => $other->id,
            'course_id' => $this->course->id,
        ]);

        LessonProgress::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'student_id' => $other->id,
            'lesson_id' => $this->lessons[0]->id,
            'status' => LessonProgressStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->assertNotContains(
            'Finished Lesson number 1',
            $this->feedLabels(),
            'The feed showed another learner what they had finished. It is scoped to the signed in '
            .'student, and the only reason that could be checked is a test that checks it.'
        );
    }

    /**
     * The feed is newest first across every source, not per source.
     *
     * An older lesson must not sit above a newer enrollment just because lessons
     * are merged after enrollments. A feed that is ordered by category rather
     * than by time is a list, not a history.
     */
    public function test_the_feed_stays_newest_first_across_every_source(): void
    {
        $this->completeLesson($this->lessons[0], now()->subDays(9));

        // A much more recent event, from a different source.
        $this->enrollment->forceFill(['activated_at' => now()])->save();

        $times = app(OperationsReport::class)
            ->studentAgenda($this->student, 20)
            ->map(fn (array $entry): int => $entry['at']->getTimestamp())
            ->all();

        $sorted = $times;
        rsort($sorted);

        $this->assertSame($sorted, $times, 'The feed was not ordered newest first across its sources.');
    }

    /** A learner who has done nothing still gets an empty feed, not a broken one. */
    public function test_a_new_account_has_an_empty_feed(): void
    {
        $fresh = User::factory()->create();

        $this->assertSame(
            [],
            app(OperationsReport::class)->studentAgenda($fresh)->all()
        );
    }

    /**
     * The page must show the event, not just build it.
     */
    public function test_the_dashboard_shows_the_finished_lesson(): void
    {
        $this->completeLesson($this->lessons[0]);

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Finished Lesson number 1');
    }

    /**
     * @return list<string>
     */
    private function feedLabels(): array
    {
        return app(OperationsReport::class)
            ->studentAgenda($this->student, 20)
            ->map(fn (array $entry): string => $entry['label'])
            ->values()
            ->all();
    }

    private function completeLesson(Lesson $lesson, mixed $at = null): LessonProgress
    {
        return LessonProgress::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::Completed,
            'started_at' => ($at ?? now())->subHour(),
            'completed_at' => $at ?? now(),
        ]);
    }
}
