<?php

namespace Tests\Feature\Phase6B;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FreeEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_enrolls_in_a_published_free_course(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $response = $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");

        $response->assertRedirect(route('student.courses.index'));

        $enrollment = Enrollment::query()->firstOrFail();

        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($course->id, $enrollment->course_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNotNull($enrollment->activated_at);
        $response->assertSessionHasNoErrors();
    }

    public function test_repeated_requests_reuse_one_enrollment(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");
        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");
        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }

    public function test_draft_and_archived_courses_cannot_be_enrolled(): void
    {
        $student = $this->makeStudent();
        $draft = $this->makeFreeCourse(['status' => CourseStatus::Draft]);
        $archived = $this->makeFreeCourse(['status' => CourseStatus::Archived]);

        $this->actingAs($student)
            ->post("/student/courses/{$draft->id}/enroll")
            ->assertNotFound();

        $this->actingAs($student)
            ->post("/student/courses/{$archived->id}/enroll")
            ->assertNotFound();

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_paid_course_shows_a_message_and_creates_nothing(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 25000,
        ]);

        $this->actingAs($student)
            ->from(route('courses.show', $course))
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHasErrors('course');

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_injected_server_fields_are_ignored(): void
    {
        $student = $this->makeStudent();
        $other = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll", [
            'student_id' => $other->id,
            'status' => 'completed',
            'activated_at' => '1999-01-01 00:00:00',
            'course_id' => 999999,
        ])->assertRedirect(route('student.courses.index'));

        $enrollment = Enrollment::query()->firstOrFail();

        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($course->id, $enrollment->course_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertTrue($enrollment->activated_at->isToday());
    }

    public function test_cancelled_enrollment_is_not_reactivated(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        Enrollment::factory()->cancelled()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($student)
            ->from(route('courses.show', $course))
            ->post("/student/courses/{$course->id}/enroll")
            ->assertSessionHasErrors('course');

        $this->assertSame(EnrollmentStatus::Cancelled, Enrollment::query()->firstOrFail()->status);
    }

    public function test_my_courses_shows_only_the_students_own_enrollments(): void
    {
        $student = $this->makeStudent();
        $other = $this->makeStudent();
        $ownCourse = $this->makeFreeCourse(['title' => 'Own Course', 'status' => CourseStatus::Published]);
        $otherCourse = $this->makeFreeCourse(['title' => 'Other Course', 'status' => CourseStatus::Published]);

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $ownCourse->id,
        ]);
        Enrollment::factory()->active()->create([
            'student_id' => $other->id,
            'course_id' => $otherCourse->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Own Course')
            ->assertDontSee('Other Course')
            ->assertSee(route('courses.show', $ownCourse), false);
    }

    public function test_student_cannot_enroll_for_another_student(): void
    {
        $student = $this->makeStudent();
        $other = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll", [
            'student_id' => $other->id,
        ]);

        $this->assertSame(0, Enrollment::query()->where('student_id', $other->id)->count());
        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }

    public function test_suspended_student_cannot_enroll(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);
        $student->profile->forceFill([
            'account_status' => UserAccountStatus::Suspended,
        ])->save();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_unverified_student_cannot_enroll(): void
    {
        $student = User::factory()->unverified()->create();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_instructor_and_administrator_cannot_enroll(): void
    {
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($this->makeInstructor())
            ->post("/student/courses/{$course->id}/enroll")
            ->assertForbidden();

        $this->actingAs($this->makeAdministrator())
            ->post("/student/courses/{$course->id}/enroll")
            ->assertForbidden();

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_guest_sees_a_sign_in_link_instead_of_enroll(): void
    {
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Sign in to enroll')
            ->assertDontSee('Enroll free', false);
    }

    public function test_student_sees_enroll_before_and_enrolled_after(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Enroll free')
            ->assertDontSee('Enrolled');

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");

        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Enrolled')
            ->assertDontSee('Enroll free', false);
    }

    public function test_paid_course_shows_no_enroll_button(): void
    {
        $student = $this->makeStudent();
        $course = $this->makeFreeCourse([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 25000,
        ]);

        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Paid enrollment opens in a later release')
            ->assertDontSee('Enroll free', false);
    }

    public function test_instructor_viewer_sees_no_enroll_controls(): void
    {
        $course = $this->makeFreeCourse(['status' => CourseStatus::Published]);

        $this->actingAs($this->makeInstructor())
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertDontSee('Enroll free', false)
            ->assertDontSee('Sign in to enroll');
    }

    public function test_my_courses_page_is_protected(): void
    {
        $this->get(route('student.courses.index'))->assertRedirect(route('login'));
        $this->actingAs($this->makeInstructor())->get(route('student.courses.index'))->assertForbidden();
    }

    public function test_phase_six_b_adds_no_payment_lesson_access_or_progress_routes(): void
    {
        $this->assertFalse(Route::has('student.payments.checkout'));
        $this->assertFalse(Route::has('student.lessons.show'));
        $this->assertFalse(Route::has('student.progress.index'));
        $this->assertFalse(Route::has('student.materials.download'));
        $this->assertFalse(Route::has('student.enrollments.cancel'));
    }

    private function makeStudent(): User
    {
        return User::factory()->create();
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function makeAdministrator(): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill([
            'role' => UserRole::Administrator,
        ])->save();

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeFreeCourse(array $attributes = []): Course
    {
        return Course::factory()
            ->for(User::factory()->instructor(), 'instructor')
            ->create(array_merge([
                'course_type' => CourseType::Free,
                'price_minor' => 0,
            ], $attributes));
    }
}
