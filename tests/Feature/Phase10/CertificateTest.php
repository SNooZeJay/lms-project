<?php

namespace Tests\Feature\Phase10;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseRequirement;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_ineligible_student_receives_no_certificate(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson, false);

        $this->actingAs($student)
            ->from(route('student.courses.index'))
            ->post($this->completeUrl($course))
            ->assertSessionHasErrors('course');

        $this->assertSame(0, Certificate::query()->count());
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_eligible_student_receives_one_certificate(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)
            ->post($this->completeUrl($course))
            ->assertRedirect();

        $certificate = Certificate::query()->firstOrFail();

        $this->assertSame($student->id, $certificate->student_id);
        $this->assertSame($course->id, $certificate->course_id);
        $this->assertSame(CertificateStatus::Issued, $certificate->status);
        $this->assertSame($student->name, $certificate->student_name_snapshot);
        $this->assertSame($course->title, $certificate->course_title_snapshot);
        $this->assertStringStartsWith('ITH-', $certificate->certificate_code);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->completed_at);
    }

    public function test_repeated_completion_creates_no_duplicate(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)->post($this->completeUrl($course));
        $this->actingAs($student)->post($this->completeUrl($course));
        $this->actingAs($student)->post($this->completeUrl($course));

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_certificate_codes_are_unique(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));

        $codes = Certificate::query()->pluck('certificate_code')->all();

        $this->assertCount(1, $codes);
        $this->assertSame(1, count(array_unique($codes)));
    }

    public function test_an_incomplete_lesson_blocks_completion(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->addLesson($course, 2);
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)->post($this->completeUrl($course));

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_an_optional_lesson_does_not_block_completion(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->addLesson($course, 2, ['is_required' => false]);
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)->post($this->completeUrl($course))->assertRedirect();

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_a_draft_lesson_does_not_block_completion(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->addLesson($course, 2, ['status' => ContentStatus::Draft]);
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)->post($this->completeUrl($course))->assertRedirect();

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_a_required_quiz_must_be_passed(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $quiz = Quiz::factory()->for($course, 'course')->required()->withQuestion()->create([
            'created_by' => $course->instructor_id,
            'status' => QuizStatus::Published,
            'position' => 1,
        ]);

        $this->actingAs($student)->post($this->completeUrl($course));

        $this->assertSame(0, Certificate::query()->count());

        QuizAttempt::factory()->for($quiz, 'quiz')->passed()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
        ]);

        $this->actingAs($student)->post($this->completeUrl($course))->assertRedirect();

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_an_optional_quiz_does_not_block_completion(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        Quiz::factory()->for($course, 'course')->withQuestion()->create([
            'created_by' => $course->instructor_id,
            'status' => QuizStatus::Published,
            'position' => 1,
        ]);

        $this->actingAs($student)->post($this->completeUrl($course))->assertRedirect();

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_a_failed_quiz_attempt_does_not_count_as_a_pass(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $quiz = Quiz::factory()->for($course, 'course')->required()->withQuestion()->create([
            'created_by' => $course->instructor_id,
            'status' => QuizStatus::Published,
            'position' => 1,
        ]);

        QuizAttempt::factory()->for($quiz, 'quiz')->failed()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
        ]);

        $this->actingAs($student)->post($this->completeUrl($course));

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_requirements_default_to_all_lessons_and_required_quizzes(): void
    {
        [$student, $enrollment, $course] = $this->enrolledCourse();

        $this->actingAs($student)->post($this->completeUrl($course));

        $requirements = CourseRequirement::query()->where('course_id', $course->id)->firstOrFail();

        $this->assertTrue($requirements->require_all_lessons);
        $this->assertTrue($requirements->require_required_quizzes);
        $this->assertTrue($requirements->certificate_enabled);
    }

    public function test_certificates_can_be_disabled_for_a_course(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        CourseRequirement::query()->create([
            'course_id' => $course->id,
            'require_all_lessons' => true,
            'require_required_quizzes' => true,
            'certificate_enabled' => false,
        ]);

        $this->actingAs($student)
            ->from(route('student.courses.index'))
            ->post($this->completeUrl($course))
            ->assertSessionHasErrors('course');

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_completion_does_not_revoke_an_existing_certificate(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)->post($this->completeUrl($course));
        $certificate = Certificate::query()->firstOrFail();

        // Content changes after completion must not silently revoke anything.
        $lesson->forceFill(['title' => 'Renamed lesson'])->save();
        $this->actingAs($student)->post($this->completeUrl($course));

        $this->assertSame(CertificateStatus::Issued, $certificate->fresh()->status);
        $this->assertNull($certificate->fresh()->revoked_at);
    }

    public function test_student_sees_only_their_own_certificate(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get('/student/certificates')
            ->assertOk()
            ->assertDontSee($student->name);
    }

    public function test_certificate_page_is_owned_by_the_student(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));
        $certificate = Certificate::query()->firstOrFail();

        $this->actingAs($student)
            ->get($this->certificateUrl($certificate))
            ->assertOk()
            ->assertSee($certificate->certificate_code)
            ->assertSee($course->title);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get($this->certificateUrl($certificate))->assertForbidden();
    }

    public function test_instructor_and_administrator_cannot_complete_for_a_student(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $instructor = User::factory()->instructor()->create();
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($instructor)->post($this->completeUrl($course))->assertForbidden();
        $this->actingAs($administrator)->post($this->completeUrl($course))->assertForbidden();

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_another_student_cannot_complete_this_enrollment(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $stranger = User::factory()->create();

        // The controller resolves the acting Student's own Enrollment, so a
        // stranger is not told that this enrollment exists.
        $this->actingAs($stranger)->post($this->completeUrl($course))->assertNotFound();

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_guest_cannot_complete(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $this->post($this->completeUrl($course))->assertRedirect(route('login'));
    }

    public function test_a_cancelled_enrollment_cannot_be_completed(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $enrollment->forceFill(['status' => EnrollmentStatus::Cancelled])->save();

        $this->actingAs($student)->post($this->completeUrl($course))->assertForbidden();

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_completion_ignores_injected_certificate_fields(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);

        $this->actingAs($student)->post($this->completeUrl($course), [
            'certificate_code' => 'FORGED-CODE',
            'student_name_snapshot' => 'Somebody Else',
            'completion_date' => '1999-01-01',
            'issued_by' => 999999,
        ])->assertRedirect();

        $certificate = Certificate::query()->firstOrFail();

        $this->assertNotSame('FORGED-CODE', $certificate->certificate_code);
        $this->assertSame($student->name, $certificate->student_name_snapshot);
        $this->assertSame($student->id, $certificate->issued_by);
        $this->assertSame(now()->toDateString(), $certificate->completion_date->toDateString());
    }

    public function test_administrator_can_revoke_with_a_reason(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));
        $certificate = Certificate::query()->firstOrFail();

        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator)
            ->post($this->revokeUrl($certificate), ['reason' => 'Issued in error'])
            ->assertRedirect();

        $certificate->refresh();

        $this->assertSame(CertificateStatus::Revoked, $certificate->status);
        $this->assertNotNull($certificate->revoked_at);
        $this->assertSame('Issued in error', $certificate->revocation_reason);
    }

    public function test_revoking_twice_is_rejected(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));
        $certificate = Certificate::query()->firstOrFail();

        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator)->post($this->revokeUrl($certificate), ['reason' => 'First']);
        $this->actingAs($administrator)
            ->from('/admin')
            ->post($this->revokeUrl($certificate), ['reason' => 'Second'])
            ->assertSessionHasErrors();

        $this->assertSame('First', $certificate->fresh()->revocation_reason);
    }

    public function test_reissue_creates_a_linked_replacement(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));
        $original = Certificate::query()->firstOrFail();

        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator)->post($this->revokeUrl($original), ['reason' => 'Corrected']);
        $this->actingAs($administrator)->post($this->reissueUrl($original))->assertRedirect();

        $replacement = Certificate::query()->where('id', '!=', $original->id)->firstOrFail();

        $this->assertSame($original->id, $replacement->replaces_certificate_id);
        $this->assertSame(CertificateStatus::Issued, $replacement->status);
        $this->assertNotSame($original->certificate_code, $replacement->certificate_code);
    }

    public function test_reissue_fails_after_fresh_validation_fails(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));
        $original = Certificate::query()->firstOrFail();

        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();
        $this->actingAs($administrator)->post($this->revokeUrl($original), ['reason' => 'Revoked']);

        // A new required lesson appears after the certificate was issued.
        $this->addLesson($course, 2);

        $this->actingAs($administrator)
            ->from('/admin')
            ->post($this->reissueUrl($original))
            ->assertSessionHasErrors('certificate');

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_a_student_cannot_revoke_or_reissue(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));
        $certificate = Certificate::query()->firstOrFail();

        $this->actingAs($student)->post($this->revokeUrl($certificate), ['reason' => 'No'])->assertForbidden();
        $this->actingAs($student)->post($this->reissueUrl($certificate))->assertForbidden();
    }

    public function test_certificate_list_shows_state(): void
    {
        [$student, $enrollment, $course, $lesson] = $this->enrolledCourse();
        $this->completeLesson($enrollment, $lesson);
        $this->actingAs($student)->post($this->completeUrl($course));

        $this->actingAs($student)
            ->get('/student/certificates')
            ->assertOk()
            ->assertSee($course->title)
            ->assertSee('Valid');
    }

    public function test_phase_ten_adds_no_reporting_routes(): void
    {
        // Payments arrive in Phase 11 and reports in Phase 13, so this guard
        // now checks the identifier that is still absent.
        $this->assertFalse(Route::has('admin.reports.index'));
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Course, 3: Lesson}
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

        $lesson = $this->makeLesson($course, $module->id, 1);

        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$student, $enrollment, $course, $lesson];
    }

    private function makeLesson(Course $course, int $moduleId, int $position): Lesson
    {
        return Lesson::factory()->create([
            'module_id' => $moduleId,
            'position' => $position,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addLesson(Course $course, int $position, array $overrides = []): Lesson
    {
        $moduleId = Module::query()->where('course_id', $course->id)->firstOrFail()->id;

        $lesson = new Lesson;
        $lesson->forceFill(array_merge([
            'module_id' => $moduleId,
            'title' => 'Lesson '.$position,
            'slug' => 'lesson-'.$position.'-'.uniqid(),
            'position' => $position,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ], $overrides));
        $lesson->save();

        return $lesson;
    }

    private function completeLesson(Enrollment $enrollment, Lesson $lesson, bool $completed = true): void
    {
        $progress = new LessonProgress;
        $progress->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
            'status' => $completed ? LessonProgressStatus::Completed : LessonProgressStatus::InProgress,
            'started_at' => now(),
            'completed_at' => $completed ? now() : null,
            'last_viewed_at' => now(),
        ]);
        $progress->save();
    }

    private function completeUrl(Course $course): string
    {
        return "/student/courses/{$course->id}/complete";
    }

    private function certificateUrl(Certificate $certificate): string
    {
        return "/student/certificates/{$certificate->id}";
    }

    private function revokeUrl(Certificate $certificate): string
    {
        return "/admin/certificates/{$certificate->id}/revoke";
    }

    private function reissueUrl(Certificate $certificate): string
    {
        return "/admin/certificates/{$certificate->id}/reissue";
    }
}
