<?php

namespace Tests\Feature\Phase7B;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_archive_and_restore_a_course(): void
    {
        [$instructor, $course] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->courseArchiveUrl($course))
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(CourseStatus::Archived, $course->fresh()->status);

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->courseRestoreUrl($course))
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(CourseStatus::Draft, $course->fresh()->status);
    }

    public function test_archived_course_leaves_the_public_catalog(): void
    {
        [$instructor, $course] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->post($this->courseArchiveUrl($course))
            ->assertRedirect();

        $this->get('/courses')
            ->assertOk()
            ->assertDontSee($course->title);

        $this->get("/courses/{$course->slug}")
            ->assertNotFound();
    }

    public function test_archived_course_cannot_be_published_directly(): void
    {
        [$instructor, $course] = $this->publishedCourse();

        $this->actingAs($instructor)->post($this->courseArchiveUrl($course));

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post("/instructor/courses/{$course->id}/publish")
            ->assertSessionHasErrors();

        $this->assertSame(CourseStatus::Archived, $course->fresh()->status);
    }

    public function test_archived_course_keeps_enrollment_and_progress(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete");

        $this->actingAs($instructor)->post($this->courseArchiveUrl($course));

        $this->assertSame(1, Enrollment::query()->count());
        $this->assertSame(1, LessonProgress::query()->count());
        $this->assertSame(EnrollmentStatus::Active, Enrollment::query()->first()->status);
    }

    public function test_student_course_page_hides_archived_content(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $lesson->forceFill(['status' => ContentStatus::Archived])->save();

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertOk()
            ->assertDontSee($lesson->title);

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}/lessons/{$lesson->id}")
            ->assertForbidden();
    }

    public function test_instructor_can_archive_and_restore_a_module(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->moduleArchiveUrl($course, $module))
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(ContentStatus::Archived, $module->fresh()->status);

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->moduleRestoreUrl($course, $module))
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(ContentStatus::Draft, $module->fresh()->status);
    }

    public function test_archived_module_hides_its_lessons_from_students(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $student = $this->enrolledStudent($course);

        $this->actingAs($instructor)->post($this->moduleArchiveUrl($course, $module));

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertOk()
            ->assertDontSee($module->title)
            ->assertDontSee($lesson->title);

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}/lessons/{$lesson->id}")
            ->assertForbidden();
    }

    public function test_instructor_can_archive_and_restore_a_lesson(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->lessonArchiveUrl($course, $module, $lesson))
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(ContentStatus::Archived, $lesson->fresh()->status);

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->lessonRestoreUrl($course, $module, $lesson))
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(ContentStatus::Draft, $lesson->fresh()->status);
    }

    public function test_archived_content_stays_out_of_the_public_course_page(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)->post($this->lessonArchiveUrl($course, $module, $lesson));

        $this->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertDontSee($lesson->title);
    }

    public function test_archiving_a_module_does_not_touch_sibling_modules(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $sibling = Module::factory()->for($course, 'course')->create([
            'position' => 2,
            'status' => ContentStatus::Published,
        ]);

        $this->actingAs($instructor)->post($this->moduleArchiveUrl($course, $module));

        $this->assertSame(ContentStatus::Published, $sibling->fresh()->status);
    }

    public function test_restore_requires_the_row_to_be_archived(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->lessonRestoreUrl($course, $module, $lesson))
            ->assertSessionHasErrors();

        $this->assertSame(ContentStatus::Published, $lesson->fresh()->status);
    }

    public function test_archive_requires_the_row_to_not_be_archived(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)->post($this->courseArchiveUrl($course));

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->post($this->courseArchiveUrl($course))
            ->assertSessionHasErrors();
    }

    public function test_cross_course_module_and_lesson_ids_are_not_found(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        [, $otherCourse, $otherModule, $otherLesson] = $this->publishedCourse($instructor);

        $this->actingAs($instructor)
            ->post($this->moduleArchiveUrl($course, $otherModule))
            ->assertNotFound();

        $this->actingAs($instructor)
            ->post($this->lessonArchiveUrl($course, $otherModule, $otherLesson))
            ->assertNotFound();

        $this->actingAs($instructor)
            ->post($this->moduleArchiveUrl($otherCourse, $module))
            ->assertNotFound();

        $this->actingAs($instructor)
            ->post($this->lessonArchiveUrl($otherCourse, $otherModule, $lesson))
            ->assertNotFound();
    }

    public function test_another_instructor_cannot_archive(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $stranger = User::factory()->instructor()->create();

        $this->actingAs($stranger)->post($this->courseArchiveUrl($course))->assertForbidden();
        $this->actingAs($stranger)->post($this->moduleArchiveUrl($course, $module))->assertForbidden();
        $this->actingAs($stranger)->post($this->lessonArchiveUrl($course, $module, $lesson))->assertForbidden();

        $this->assertSame(CourseStatus::Published, $course->fresh()->status);
        $this->assertSame(ContentStatus::Published, $module->fresh()->status);
        $this->assertSame(ContentStatus::Published, $lesson->fresh()->status);
    }

    public function test_student_administrator_and_guest_cannot_archive(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();
        $student = $this->enrolledStudent($course);
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->post($this->courseArchiveUrl($course))->assertRedirect(route('login'));
        $this->post($this->moduleArchiveUrl($course, $module))->assertRedirect(route('login'));
        $this->post($this->lessonArchiveUrl($course, $module, $lesson))->assertRedirect(route('login'));

        $this->actingAs($student)->post($this->courseArchiveUrl($course))->assertForbidden();
        $this->actingAs($administrator)->post($this->courseArchiveUrl($course))->assertForbidden();

        $this->actingAs($student)->post($this->moduleArchiveUrl($course, $module))->assertForbidden();
        $this->actingAs($student)->post($this->lessonArchiveUrl($course, $module, $lesson))->assertForbidden();

        $this->assertSame(CourseStatus::Published, $course->fresh()->status);
    }

    public function test_archive_ignores_injected_status_and_owner_fields(): void
    {
        [$instructor, $course] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->post($this->courseArchiveUrl($course), [
                'status' => 'published',
                'instructor_id' => 999999,
                'published_at' => null,
            ])
            ->assertRedirect();

        $this->assertSame(CourseStatus::Archived, $course->fresh()->status);
    }

    public function test_outline_shows_archive_and_restore_controls(): void
    {
        [$instructor, $course, $module, $lesson] = $this->publishedCourse();

        $this->actingAs($instructor)
            ->get($this->courseShowUrl($course))
            ->assertOk()
            ->assertSee('Archive course')
            ->assertSee($this->courseArchiveUrl($course), false)
            ->assertSee('Archive module')
            ->assertSee($this->moduleArchiveUrl($course, $module), false)
            ->assertSee('Archive lesson')
            ->assertSee($this->lessonArchiveUrl($course, $module, $lesson), false);
    }

    public function test_archived_course_page_offers_restore(): void
    {
        [$instructor, $course] = $this->publishedCourse();

        $this->actingAs($instructor)->post($this->courseArchiveUrl($course));

        $this->actingAs($instructor)
            ->get($this->courseShowUrl($course))
            ->assertOk()
            ->assertSee('Restore course')
            ->assertSee('Archived')
            ->assertSee($this->courseRestoreUrl($course), false)
            ->assertDontSee('Publish course');
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson}
     */
    private function publishedCourse(?User $instructor = null): array
    {
        $instructor ??= User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->for($module, 'module')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        return [$instructor, $course, $module, $lesson];
    }

    private function enrolledStudent(Course $course): User
    {
        $student = User::factory()->create();

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return $student;
    }

    private function courseShowUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}";
    }

    private function courseArchiveUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}/archive";
    }

    private function courseRestoreUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}/restore";
    }

    private function moduleArchiveUrl(Course $course, Module $module): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/archive";
    }

    private function moduleRestoreUrl(Course $course, Module $module): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/restore";
    }

    private function lessonArchiveUrl(Course $course, Module $module, Lesson $lesson): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/archive";
    }

    private function lessonRestoreUrl(Course $course, Module $module, Lesson $lesson): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/restore";
    }
}
