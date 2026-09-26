<?php

namespace Tests\Feature\Phase6C;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningMaterialType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentLessonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_opens_a_published_lesson_and_reads_content(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('How HTTP Works')
            ->assertSee('A short summary for the student.')
            ->assertSee('Lesson body content for the student.')
            ->assertSee('30 min')
            ->assertSee('Required');
    }

    public function test_lesson_page_shows_learning_materials(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();

        LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'Reading Notes',
            'material_type' => LearningMaterialType::Text,
            'position' => 1,
            'content_text' => 'Material text content.',
        ]);
        LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'PHP Manual',
            'material_type' => LearningMaterialType::ExternalLink,
            'position' => 2,
            'external_url' => 'https://www.php.net/manual/en/',
        ]);

        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('Reading Notes')
            ->assertSee('Material text content.')
            ->assertSee('PHP Manual')
            ->assertSee('https://www.php.net/manual/en/', false);
    }

    public function test_student_course_page_lists_published_lessons_only(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        $draftLesson = Lesson::factory()->for($module, 'module')->create([
            'title' => 'Draft Lesson',
            'position' => 2,
            'status' => ContentStatus::Draft,
        ]);

        $this->actingAs($student)
            ->get(route('student.courses.show', $course))
            ->assertOk()
            ->assertSee('How HTTP Works')
            ->assertSee($this->lessonUrl($course, $lesson), false)
            ->assertDontSee('Draft Lesson')
            ->assertDontSee($this->lessonUrl($course, $draftLesson), false);
    }

    public function test_student_without_enrollment_is_forbidden(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();

        $this->actingAs($student)
            ->get(route('student.courses.show', $course))
            ->assertForbidden();

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertForbidden();
    }

    public function test_student_enrolled_in_another_course_is_forbidden(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        [$otherCourse] = $this->makePublishedCourse();
        $this->enroll($student, $otherCourse);

        $this->actingAs($student)->get($this->lessonUrl($course, $lesson))->assertForbidden();
    }

    public function test_pending_or_cancelled_enrollment_does_not_grant_access(): void
    {
        [$course, $module, $lesson] = $this->makePublishedCourse();

        foreach ([EnrollmentStatus::PendingPayment, EnrollmentStatus::Cancelled] as $status) {
            $student = User::factory()->create();
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => $status,
            ]);

            $this->actingAs($student)->get($this->lessonUrl($course, $lesson))->assertForbidden();
        }
    }

    public function test_completed_enrollment_still_grants_access(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();

        Enrollment::factory()->completed()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('How HTTP Works');
    }

    public function test_draft_lesson_or_draft_module_is_forbidden(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        $lesson->forceFill(['status' => ContentStatus::Draft])->save();
        $this->actingAs($student)->get($this->lessonUrl($course, $lesson))->assertForbidden();

        $lesson->forceFill(['status' => ContentStatus::Published])->save();
        $module->forceFill(['status' => ContentStatus::Draft])->save();
        $this->actingAs($student)->get($this->lessonUrl($course, $lesson))->assertForbidden();
    }

    public function test_lesson_from_another_course_in_the_url_is_not_found(): void
    {
        $student = User::factory()->create();
        [$course] = $this->makePublishedCourse();
        [$otherCourse, $otherModule, $otherLesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}/lessons/{$otherLesson->id}")
            ->assertNotFound();

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}/lessons/{$otherLesson->id}/edit")
            ->assertNotFound();
    }

    public function test_unpublished_course_still_allows_enrolled_student_access(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        $course->forceFill(['status' => CourseStatus::Draft])->save();
        $lesson->forceFill(['status' => ContentStatus::Draft])->save();
        $lesson->forceFill(['status' => ContentStatus::Published])->save();

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('How HTTP Works');
    }

    public function test_guest_is_redirected_to_sign_in(): void
    {
        [$course, $module, $lesson] = $this->makePublishedCourse();

        $this->get(route('student.courses.show', $course))->assertRedirect(route('login'));
        $this->get($this->lessonUrl($course, $lesson))->assertRedirect(route('login'));
    }

    public function test_suspended_and_unverified_students_are_blocked(): void
    {
        [$course, $module, $lesson] = $this->makePublishedCourse();

        $suspended = User::factory()->create();
        $this->enroll($suspended, $course);
        $suspended->profile->forceFill([
            'account_status' => UserAccountStatus::Suspended,
        ])->save();

        $this->actingAs($suspended)->get($this->lessonUrl($course, $lesson))->assertRedirect('/login');
        $this->assertGuest();

        $unverified = User::factory()->unverified()->create();
        $this->enroll($unverified, $course);

        $this->actingAs($unverified)
            ->get($this->lessonUrl($course, $lesson))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_instructor_and_administrator_cannot_use_student_lesson_pages(): void
    {
        [$course, $module, $lesson] = $this->makePublishedCourse();

        $this->actingAs(User::factory()->instructor()->create())
            ->get($this->lessonUrl($course, $lesson))
            ->assertForbidden();

        $administrator = User::factory()->create();
        $administrator->profile->forceFill([
            'role' => UserRole::Administrator,
        ])->save();

        $this->actingAs($administrator->refresh())
            ->get(route('student.courses.show', $course))
            ->assertForbidden();
    }

    public function test_material_storage_details_never_reach_a_student_page(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'Stored File Material',
            'material_type' => LearningMaterialType::Pdf,
            'position' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'materials/secret.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 2048,
        ]);

        $this->actingAs($student)
            ->get($this->lessonUrl($course, $lesson))
            ->assertOk()
            ->assertSee('Stored File Material')
            ->assertDontSee('materials/secret.pdf')
            ->assertDontSee('application/pdf');
    }

    public function test_my_courses_links_to_the_student_course_page(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makePublishedCourse();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee(route('student.courses.show', $course), false);
    }

    public function test_phase_six_c_adds_no_progress_quiz_payment_or_download_routes(): void
    {
        $this->assertFalse(Route::has('student.progress.index'));
        $this->assertFalse(Route::has('student.payments.checkout'));
        $this->assertFalse(Route::has('instructor.courses.students'));
    }

    /**
     * @return array{0: Course, 1: Module, 2: Lesson}
     */
    private function makePublishedCourse(): array
    {
        $course = Course::factory()
            ->for(User::factory()->instructor(), 'instructor')
            ->create([
                'status' => CourseStatus::Published,
                'course_type' => CourseType::Free,
                'price_minor' => 0,
                'title' => 'Web Fundamentals',
            ]);

        $module = Module::factory()->for($course, 'course')->create([
            'title' => 'Module One',
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->for($module, 'module')->create([
            'title' => 'How HTTP Works',
            'summary' => 'A short summary for the student.',
            'content_text' => 'Lesson body content for the student.',
            'estimated_minutes' => 30,
            'is_required' => true,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        return [$course, $module, $lesson];
    }

    private function enroll(User $student, Course $course): Enrollment
    {
        return Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    private function lessonUrl(Course $course, Lesson $lesson): string
    {
        return "/student/courses/{$course->id}/lessons/{$lesson->id}";
    }
}
