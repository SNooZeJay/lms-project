<?php

namespace Tests\Feature\Phase8;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Support\ContinueLearning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ContinueLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_links_to_the_most_recent_lesson(): void
    {
        [$student, $course, $module, $older, $newer] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $older, now()->subDays(3));
        $this->recordProgress($student, $course, $newer, now()->subMinutes(5));

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Continue learning')
            ->assertSee($newer->title)
            ->assertSee($course->title)
            ->assertSee(route('student.lessons.show', [$course, $newer]), false);
    }

    public function test_the_most_recent_lesson_wins_regardless_of_completion(): void
    {
        [$student, $course, $module, $first, $second] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $first, now()->subDays(5), LessonProgressStatus::Completed);
        $this->recordProgress($student, $course, $second, now()->subHour());

        $card = $this->continueLearningCard($this->actingAs($student)->get(route('student.dashboard'))->assertOk());

        $this->assertNotNull($card, 'The Continue learning card was not rendered at all.');
        $this->assertStringContainsString($second->title, $card);
        $this->assertStringNotContainsString(
            $first->title,
            $card,
            'The card offered the older finished lesson instead of the one opened an hour ago.'
        );
    }

    public function test_a_student_with_no_history_sees_an_empty_state(): void
    {
        [$student] = $this->enrolledCourse();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No lessons opened yet')
            ->assertSee(route('student.courses.index'), false);
    }

    public function test_another_students_history_is_never_offered(): void
    {
        [$student, $course, $module, $first] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $first, now()->subHour());

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee($first->title)
            ->assertSee('No lessons opened yet');
    }

    public function test_a_draft_lesson_is_not_offered(): void
    {
        [$student, $course, $module, $first, $second] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $first, now()->subDays(2));
        $this->recordProgress($student, $course, $second, now()->subMinute());
        $second->forceFill(['status' => ContentStatus::Draft])->save();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($first->title)
            ->assertDontSee($second->title);
    }

    public function test_a_draft_module_is_not_offered(): void
    {
        [$student, $course, $module, $first, $second] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $first, now()->subDays(2));
        $this->recordProgress($student, $course, $second, now()->subMinute());

        // Move the newer Lesson into its own Module and draft that Module.
        $otherModule = Module::factory()->for($course, 'course')->create([
            'position' => 2,
            'status' => ContentStatus::Draft,
        ]);
        $second->forceFill(['module_id' => $otherModule->id])->save();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($first->title)
            ->assertDontSee($second->title);
    }

    public function test_an_unpublished_course_is_not_offered(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());
        $course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No lessons opened yet');
    }

    public function test_an_unpublished_course_hides_the_card_but_keeps_access_to_the_lesson(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());
        $course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Continue learning');

        $this->actingAs($student)
            ->get(route('student.lessons.show', [$course, $lesson]))
            ->assertOk();
    }

    public function test_a_cancelled_enrollment_is_not_offered(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());
        Enrollment::query()->update(['status' => EnrollmentStatus::Cancelled]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No lessons opened yet');
    }

    public function test_history_from_a_course_the_student_left_is_not_offered(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());

        $otherCourse = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);
        $otherLesson = $this->lessonFor($otherCourse);
        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $otherCourse->id,
        ]);
        $this->recordProgress($student, $otherCourse, $otherLesson, now()->subMinute());

        // The Student leaves that course, so it is no longer a return point.
        Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $otherCourse->id)
            ->update(['status' => EnrollmentStatus::Cancelled]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($lesson->title)
            ->assertDontSee($otherLesson->title);
    }

    public function test_the_service_ignores_a_progress_row_whose_lesson_vanished(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());

        LessonProgress::query()->update(['last_viewed_at' => null]);

        $this->assertNull(ContinueLearning::forStudent($student));
    }

    public function test_the_service_ignores_a_row_with_no_last_viewed_time(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());
        LessonProgress::query()->update(['last_viewed_at' => null]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No lessons opened yet');
    }

    public function test_the_card_links_only_to_a_lesson_the_student_can_open(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(route('student.lessons.show', [$course, $lesson]), false);

        $this->actingAs($student)
            ->get(route('student.lessons.show', [$course, $lesson]))
            ->assertOk();
    }

    public function test_instructor_and_administrator_never_see_the_student_card(): void
    {
        $instructor = User::factory()->instructor()->create();
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertDontSee('Continue learning');

        $this->actingAs($administrator)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertDontSee('Continue learning');
    }

    public function test_a_lesson_with_no_content_still_appears(): void
    {
        [$student, $course, $module, $lesson] = $this->enrolledCourse();
        $lesson->forceFill(['content_text' => null])->save();
        $this->recordProgress($student, $course, $lesson, now()->subHour());

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($lesson->title);
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson, 4: Lesson}
    /**
     * The card must not tell a learner two opposite things at once.
     *
     * The panel badged the lesson "Completed" and then offered a primary button
     * reading "Resume this lesson", on the same card, at the same time. Both
     * sentences were true and together they said nothing: a learner who has
     * finished a lesson is not resuming it.
     *
     * The service is not at fault and was not changed. `ContinueLearning` returns
     * the most recently opened lesson the learner can still open, and offering
     * a finished lesson for reference is reasonable. What was wrong was the
     * wording around it, so the wording is what is pinned here.
     */
    public function test_a_finished_lesson_is_offered_for_reference_and_not_as_something_to_resume(): void
    {
        [$student, $course, , $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour(), LessonProgressStatus::Completed);

        $card = $this->continueLearningCard($this->actingAs($student)->get(route('student.dashboard'))->assertOk());

        $this->assertNotNull($card);
        $this->assertStringContainsString('Completed', $card);
        $this->assertStringContainsString('Review this lesson', $card);
        $this->assertStringNotContainsString('Resume this lesson', $card);
    }

    /** The unfinished case keeps the word "resume", because that is what it is. */
    public function test_an_unfinished_lesson_is_still_offered_as_something_to_resume(): void
    {
        [$student, $course, , $lesson] = $this->enrolledCourse();
        $this->recordProgress($student, $course, $lesson, now()->subHour());

        $card = $this->continueLearningCard($this->actingAs($student)->get(route('student.dashboard'))->assertOk());

        $this->assertNotNull($card);
        $this->assertStringContainsString('Resume this lesson', $card);
        $this->assertStringNotContainsString('Review this lesson', $card);
    }

    /**
     * The Continue learning card on its own, with the rest of the page removed.
     *
     * The card used to be checked with a page-wide `assertSee`, on the grounds
     * that a lesson title appears on the Student dashboard only when the card
     * offers it. That stopped being true when the activity feed began listing
     * finished lessons, which it should: a learner who finished a lesson sees it
     * in their history whether or not the card is pointing at it.
     *
     * So the assertion is read from the card itself. Without this, a correct
     * change to an unrelated panel would be reported as a failure here, and the
     * fix would have been to delete a correct assertion.
     */
    private function continueLearningCard(TestResponse $response): ?string
    {
        $html = (string) $response->getContent();

        if (! preg_match('~<section[^>]*aria-labelledby="continue-learning-heading".*?</section>~s', $html, $found)) {
            return null;
        }

        return $found[0];
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson, 4: Lesson}
     */
    private function enrolledCourse(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $first = $this->lessonFor($course, $module, 1);
        $second = $this->lessonFor($course, $module, 2);

        $student = User::factory()->create();

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$student, $course, $module, $first, $second];
    }

    private function lessonFor(Course $course, ?Module $module = null, int $position = 1): Lesson
    {
        $module ??= Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        return Lesson::factory()->for($module, 'module')->create([
            'position' => $position,
            'status' => ContentStatus::Published,
            'is_required' => true,
            'content_text' => 'Body',
        ]);
    }

    private function recordProgress(
        User $student,
        Course $course,
        Lesson $lesson,
        $lastViewedAt,
        LessonProgressStatus $status = LessonProgressStatus::InProgress,
    ): void {
        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $progress = new LessonProgress;
        $progress->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => $status,
            'started_at' => $lastViewedAt,
            'completed_at' => $status === LessonProgressStatus::Completed ? $lastViewedAt : null,
            'last_viewed_at' => $lastViewedAt,
        ]);
        $progress->save();
    }
}
