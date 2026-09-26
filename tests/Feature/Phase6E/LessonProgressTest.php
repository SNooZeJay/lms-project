<?php

namespace Tests\Feature\Phase6E;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_lesson_records_activity_without_completing_it(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)->get($this->lessonUrl($course, $lesson))->assertOk();

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame(LessonProgressStatus::InProgress, $progress->status);
        $this->assertNotNull($progress->started_at);
        $this->assertNotNull($progress->last_viewed_at);
        $this->assertNull($progress->completed_at);
    }

    public function test_student_can_mark_a_lesson_complete(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $response = $this->actingAs($student)
            ->from($this->lessonUrl($course, $lesson))
            ->post($this->completeUrl($course, $lesson));

        $response->assertRedirect($this->lessonUrl($course, $lesson));

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame(LessonProgressStatus::Completed, $progress->status);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_completing_twice_keeps_one_record_and_the_first_completion_time(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));
        $firstCompletedAt = LessonProgress::query()->firstOrFail()->completed_at;

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame(1, LessonProgress::query()->count());
        $this->assertEquals($firstCompletedAt, $progress->completed_at);
    }

    public function test_viewing_a_completed_lesson_keeps_it_completed(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));
        $completedAt = LessonProgress::query()->firstOrFail()->completed_at;

        $this->actingAs($student)->get($this->lessonUrl($course, $lesson))->assertOk();

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame(LessonProgressStatus::Completed, $progress->status);
        $this->assertEquals($completedAt, $progress->completed_at);
    }

    public function test_completion_ignores_injected_server_fields(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();
        $other = $this->makeStudent();

        $this->actingAs($student)->post($this->completeUrl($course, $lesson), [
            'student_id' => $other->id,
            'enrollment_id' => 999999,
            'status' => 'completed',
            'completed_at' => '1999-01-01 00:00:00',
            'percentage' => 100,
        ])->assertRedirect($this->lessonUrl($course, $lesson));

        $progress = LessonProgress::query()->firstOrFail();

        $this->assertSame($student->id, $progress->student_id);
        $this->assertSame(LessonProgressStatus::Completed, $progress->status);
        $this->assertTrue($progress->completed_at->isToday());
    }

    public function test_percentage_counts_required_published_lessons(): void
    {
        [$student, $course, $module, $lesson] = $this->makeEnrolledCourse();

        // One more required published lesson.
        Lesson::factory()->for($module, 'module')->create([
            'position' => 2,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        $this->actingAs($student)->get($this->courseUrl($course))->assertOk()->assertSee('0%');
        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $this->actingAs($student)
            ->get($this->courseUrl($course))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('Completed');
    }

    public function test_optional_lessons_do_not_change_the_percentage(): void
    {
        [$student, $course, $module, $lesson] = $this->makeEnrolledCourse();

        Lesson::factory()->for($module, 'module')->create([
            'position' => 2,
            'status' => ContentStatus::Published,
            'is_required' => false,
        ]);

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $this->actingAs($student)
            ->get($this->courseUrl($course))
            ->assertOk()
            ->assertSee('100%')
            ->assertDontSee('50%');
    }

    public function test_lessons_in_draft_modules_do_not_count(): void
    {
        [$student, $course, $module, $lesson] = $this->makeEnrolledCourse();

        $draftModule = Module::factory()->for($course, 'course')->create([
            'position' => 2,
            'status' => ContentStatus::Draft,
        ]);
        Lesson::factory()->for($draftModule, 'module')->create([
            'position' => 1,
            'status' => ContentStatus::Draft,
            'is_required' => true,
        ]);

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $this->actingAs($student)
            ->get($this->courseUrl($course))
            ->assertOk()
            ->assertSee('100%');
    }

    public function test_course_without_required_published_lessons_shows_zero_percent(): void
    {
        [$student, $course, $module] = $this->makeEnrolledCourse();

        Lesson::query()->update(['is_required' => false]);

        $this->actingAs($student)
            ->get($this->courseUrl($course))
            ->assertOk()
            ->assertSee('0%');
    }

    public function test_my_courses_shows_the_percentage(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('0%');

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('100%');
    }

    public function test_unpublished_course_hides_the_percentage_and_keeps_records(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));
        $this->assertSame(1, LessonProgress::query()->count());

        $course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->actingAs($student)
            ->get($this->courseUrl($course))
            ->assertOk()
            ->assertSee('not published')
            ->assertDontSee('100%');

        $course->forceFill(['status' => CourseStatus::Published])->save();

        $this->actingAs($student)
            ->get($this->courseUrl($course))
            ->assertOk()
            ->assertSee('100%');

        $this->assertSame(1, LessonProgress::query()->count());
    }

    public function test_another_student_cannot_mark_a_lesson_complete(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();
        $other = $this->makeStudent();

        $this->actingAs($other)
            ->post($this->completeUrl($course, $lesson))
            ->assertForbidden();

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_cancelled_or_pending_enrollment_cannot_complete(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        foreach ([EnrollmentStatus::Cancelled, EnrollmentStatus::PendingPayment] as $status) {
            Enrollment::query()->update(['status' => $status]);

            $this->actingAs($student)
                ->post($this->completeUrl($course, $lesson))
                ->assertForbidden();
        }

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_draft_lesson_cannot_be_completed(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();
        $lesson->forceFill(['status' => ContentStatus::Draft])->save();

        $this->actingAs($student)
            ->post($this->completeUrl($course, $lesson))
            ->assertForbidden();

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_cross_course_lesson_id_is_not_found(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();
        $otherLesson = Lesson::factory()->create();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/lessons/{$otherLesson->id}/complete")
            ->assertNotFound();

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_forbidden_requests_do_not_record_activity(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();
        $other = $this->makeStudent();

        $this->actingAs($other)->get($this->lessonUrl($course, $lesson))->assertForbidden();

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_instructor_and_administrator_cannot_complete(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs(User::factory()->instructor()->create())
            ->post($this->completeUrl($course, $lesson))
            ->assertForbidden();

        $administrator = $this->makeStudent();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator->refresh())
            ->post($this->completeUrl($course, $lesson))
            ->assertForbidden();

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_guest_suspended_and_unverified_students_are_blocked(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->post($this->completeUrl($course, $lesson))->assertRedirect(route('login'));

        $suspended = $this->makeEnrolledStudentFor($course);
        $suspended->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();
        $this->actingAs($suspended)->post($this->completeUrl($course, $lesson))->assertRedirect('/login');

        $unverified = User::factory()->unverified()->create();
        Enrollment::factory()->active()->create([
            'student_id' => $unverified->id,
            'course_id' => $course->id,
        ]);
        $this->actingAs($unverified)
            ->post($this->completeUrl($course, $lesson))
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, LessonProgress::query()->count());
    }

    public function test_lesson_page_shows_completed_state_after_completing(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('Mark as complete')
            ->assertDontSee('Completed');

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('Completed')
            ->assertDontSee('Mark as complete');
    }

    public function test_phase_six_e_adds_only_the_lesson_complete_route(): void
    {
        $this->assertTrue(Route::has('student.lessons.complete'));
        $this->assertFalse(Route::has('student.quizzes.index'));
        $this->assertFalse(Route::has('student.certificates.index'));
        $this->assertFalse(Route::has('student.payments.checkout'));
        $this->assertFalse(Route::has('student.continue.index'));
    }

    public function test_no_page_still_claims_progress_is_missing(): void
    {
        [$student, $course, , $lesson] = $this->makeEnrolledCourse();

        $this->actingAs($student)->post($this->completeUrl($course, $lesson));

        $pages = [
            route('home'),
            route('courses.index'),
            route('student.dashboard'),
            route('student.courses.index'),
            $this->courseUrl($course),
            $this->lessonUrl($course, $lesson),
        ];

        foreach ($pages as $url) {
            $body = $this->actingAs($student)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsStringIgnoringCase(
                'progress tracking is not built',
                $body,
                "Page {$url} still says progress tracking is not built."
            );

            $this->assertStringNotContainsStringIgnoringCase(
                'progress, quizzes, uploads, and payments are not enabled',
                $body,
                "Page {$url} still says progress is not enabled."
            );

            $this->assertStringNotContainsStringIgnoringCase(
                'progress is not built',
                $body,
                "Page {$url} still says progress is not built."
            );
        }
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson}
     */
    private function makeEnrolledCourse(): array
    {
        $course = Course::factory()
            ->for(User::factory()->instructor(), 'instructor')
            ->create([
                'status' => CourseStatus::Published,
                'course_type' => CourseType::Free,
                'price_minor' => 0,
                'title' => 'Progress Course',
            ]);

        $module = Module::factory()->for($course, 'course')->create([
            'status' => ContentStatus::Published,
            'position' => 1,
        ]);

        $lesson = Lesson::factory()->for($module, 'module')->create([
            'status' => ContentStatus::Published,
            'position' => 1,
            'is_required' => true,
        ]);

        $student = $this->makeStudent();
        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$student, $course, $module, $lesson];
    }

    private function makeEnrolledStudentFor(Course $course): User
    {
        $student = $this->makeStudent();
        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return $student;
    }

    private function makeStudent(): User
    {
        return User::factory()->create();
    }

    private function courseUrl(Course $course): string
    {
        return "/student/courses/{$course->id}";
    }

    private function lessonUrl(Course $course, Lesson $lesson): string
    {
        return "/student/courses/{$course->id}/lessons/{$lesson->id}";
    }

    private function completeUrl(Course $course, Lesson $lesson): string
    {
        return $this->lessonUrl($course, $lesson).'/complete';
    }
}
