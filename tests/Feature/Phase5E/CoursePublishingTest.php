<?php

namespace Tests\Feature\Phase5E;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\UserAccountStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CoursePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_publish_an_owned_course_with_content(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $response = $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/publish");

        $response->assertRedirect(route('instructor.courses.show', $course));
        $response->assertSessionHasNoErrors();

        $course->refresh();

        $this->assertSame(CourseStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);
        $this->assertSame(ContentStatus::Published, $course->modules()->first()->status);
        $this->assertSame(ContentStatus::Published, $course->lessons()->first()->status);
    }

    public function test_publishing_requires_at_least_one_module_and_lesson(): void
    {
        $instructor = $this->makeInstructor();

        $emptyCourse = $this->makeCourse($instructor);
        $moduleOnlyCourse = $this->makeCourse($instructor);
        Module::factory()->for($moduleOnlyCourse, 'course')->create();

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$emptyCourse->id}/publish")
            ->assertSessionHasErrors('status');

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$moduleOnlyCourse->id}/publish")
            ->assertSessionHasErrors('status');

        $this->assertSame(CourseStatus::Draft, $emptyCourse->refresh()->status);
        $this->assertNull($emptyCourse->published_at);
        $this->assertSame(CourseStatus::Draft, $moduleOnlyCourse->refresh()->status);
        $this->assertNull($moduleOnlyCourse->published_at);
    }

    public function test_a_published_course_cannot_be_published_again(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/publish");
        $firstPublishedAt = $course->refresh()->published_at;

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/publish")
            ->assertSessionHasErrors('status');

        $course->refresh();

        $this->assertSame(CourseStatus::Published, $course->status);
        $this->assertEquals($firstPublishedAt, $course->published_at);
    }

    public function test_instructor_can_unpublish_and_content_returns_to_draft(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/publish");
        $publishedAt = $course->refresh()->published_at;

        $response = $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/unpublish");

        $response->assertRedirect(route('instructor.courses.show', $course));

        $course->refresh();

        $this->assertSame(CourseStatus::Draft, $course->status);
        $this->assertSame(ContentStatus::Draft, $course->modules()->first()->status);
        $this->assertSame(ContentStatus::Draft, $course->lessons()->first()->status);
        $this->assertEquals($publishedAt, $course->published_at);
    }

    public function test_a_draft_course_cannot_be_unpublished(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/unpublish")
            ->assertSessionHasErrors('status');

        $this->assertSame(CourseStatus::Draft, $course->refresh()->status);
    }

    public function test_publishing_ignores_injected_server_fields(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/publish", [
            'status' => 'archived',
            'published_at' => '1999-01-01 00:00:00',
            'instructor_id' => 999999,
        ])->assertRedirect(route('instructor.courses.show', $course));

        $course->refresh();

        $this->assertSame(CourseStatus::Published, $course->status);
        $this->assertSame($instructor->id, $course->instructor_id);
        $this->assertTrue($course->published_at->isToday());
    }

    public function test_instructor_cannot_publish_another_instructors_course(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($this->makeInstructor());

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/publish")
            ->assertForbidden();

        $this->assertSame(CourseStatus::Draft, $course->refresh()->status);
        $this->assertNull($course->published_at);
    }

    public function test_student_cannot_publish_or_unpublish(): void
    {
        $student = User::factory()->create();
        $course = $this->makeCourseWithContent($this->makeInstructor());

        $this->actingAs($student)
            ->post("/instructor/courses/{$course->id}/publish")
            ->assertForbidden();

        $this->assertSame(CourseStatus::Draft, $course->refresh()->status);
    }

    public function test_suspended_instructor_cannot_publish(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);
        $instructor->profile->forceFill([
            'account_status' => UserAccountStatus::Suspended,
        ])->save();

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/publish")
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(CourseStatus::Draft, $course->refresh()->status);
    }

    public function test_outline_page_shows_publish_control_for_a_draft_course(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Publish course')
            ->assertSee(route('instructor.courses.publish', $course), false)
            ->assertDontSee(route('instructor.courses.unpublish', $course), false);
    }

    public function test_outline_page_shows_unpublish_control_for_a_published_course(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourseWithContent($instructor);

        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/publish");

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Unpublish course')
            ->assertSee(route('instructor.courses.unpublish', $course), false)
            ->assertDontSee(route('instructor.courses.publish', $course), false);
    }

    public function test_course_list_shows_the_right_control_per_status(): void
    {
        $instructor = $this->makeInstructor();
        $draft = $this->makeCourseWithContent($instructor);
        $published = $this->makeCourseWithContent($instructor);
        $this->actingAs($instructor)->post("/instructor/courses/{$published->id}/publish");

        $this->actingAs($instructor)
            ->get(route('instructor.courses.index'))
            ->assertOk()
            ->assertSee(route('instructor.courses.publish', $draft), false)
            ->assertSee(route('instructor.courses.unpublish', $published), false);
    }

    public function test_phase_five_e_adds_no_enrollment_or_archive_routes(): void
    {
        $this->assertFalse(Route::has('instructor.courses.archive'));
        $this->assertFalse(Route::has('instructor.courses.destroy'));
        $this->assertFalse(Route::has('student.enrollments.store'));
        $this->assertFalse(Route::has('student.payments.checkout'));
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function makeCourse(User $instructor): Course
    {
        return Course::factory()->for($instructor, 'instructor')->create();
    }

    private function makeCourseWithContent(User $instructor): Course
    {
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create();
        Lesson::factory()->for($module, 'module')->create();

        return $course;
    }
}
