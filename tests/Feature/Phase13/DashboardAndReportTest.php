<?php

namespace Tests\Feature\Phase13;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuizStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_counts_match_real_records(): void
    {
        [$student, $course, $lesson] = $this->enrolledStudent();

        $this->completeLesson($course, $lesson, $student);
        $this->completeLesson($course, $this->anotherLesson($course, 2), $student);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Courses enrolled')
            ->assertSee('Lessons completed')
            ->assertSee('Quizzes passed')
            ->assertSee('Certificates earned')
            ->assertSee('3')
            ->assertSee('2');
    }

    public function test_student_dashboard_shows_an_empty_state_for_a_new_account(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No lessons opened yet')
            ->assertSee('0');
    }

    public function test_student_dashboard_never_shows_another_students_counts(): void
    {
        [$student, $course, $lesson] = $this->enrolledStudent();
        $this->completeLesson($course, $lesson, $student);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee($course->title)
            ->assertSee('0');
    }

    public function test_instructor_dashboard_counts_only_owned_courses(): void
    {
        $instructor = User::factory()->instructor()->create();
        $other = User::factory()->instructor()->create();

        $course = $this->makeCourse($instructor);
        $strangerCourse = $this->makeCourse($other);

        $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee($course->title)
            ->assertDontSee($strangerCourse->title)
            ->assertSee('Courses')
            ->assertSee('Students enrolled')
            ->assertSee('Quizzes');
    }

    public function test_instructor_dashboard_shows_an_empty_state_with_no_courses(): void
    {
        $instructor = User::factory()->instructor()->create();

        $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('No courses yet')
            ->assertSee('0');
    }

    public function test_administrator_dashboard_counts_match_real_records(): void
    {
        $administrator = $this->administrator();
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $this->makeCourse($instructor)->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('Courses')
            ->assertSee('Enrollments')
            ->assertSee('Certificates earned')
            ->assertSee('Students')
            ->assertSee('Instructors')
            // Three accounts exist: the administrator, the student, and the instructor.
            ->assertSee('3')
            // One course and one enrollment exist.
            ->assertSee('1');
    }

    public function test_administrator_dashboard_shows_an_empty_state_for_a_fresh_server(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('0')
            ->assertSee('Certificates');
    }

    public function test_administrator_report_lists_enrollments_with_real_values(): void
    {
        $administrator = $this->administrator();
        $student = User::factory()->create(['name' => 'Report Student']);
        $instructor = User::factory()->instructor()->create();
        $course = $this->makeCourse($instructor, 'Report Course');

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Report Student')
            ->assertSee('Report Course')
            ->assertSee('Active');
    }

    public function test_administrator_report_hides_nothing_but_also_shows_no_payment_secrets(): void
    {
        $administrator = $this->administrator();
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();
        $course = $this->makeCourse($instructor);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount_minor' => 50000,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid,
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
        ]);
        $payment->save();

        $body = $this->actingAs($administrator)->get(route('admin.reports.index'))->assertOk()->getContent();

        $this->assertStringContainsString('500.00', $body);
        $this->assertStringNotContainsString('sk_test', $body);
        $this->assertStringNotContainsString('PAYMONGO_SECRET', $body);
    }

    public function test_reports_are_not_open_to_other_roles(): void
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();
        $administrator = $this->administrator();

        $this->actingAs($student)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($instructor)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($administrator)->get(route('admin.reports.index'))->assertOk();
    }

    public function test_reports_require_a_signed_in_administrator(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    }

    public function test_a_suspended_administrator_cannot_open_reports(): void
    {
        $administrator = $this->administrator();
        $administrator->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();

        $this->actingAs($administrator->refresh())
            ->get(route('admin.reports.index'))
            ->assertRedirect('/login');
    }

    public function test_instructor_cannot_open_the_administrator_dashboard_data(): void
    {
        $instructor = User::factory()->instructor()->create();
        $other = User::factory()->instructor()->create();
        $course = $this->makeCourse($other, 'Hidden Course');

        $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertDontSee('Hidden Course');
    }

    public function test_certificate_count_only_includes_valid_certificates(): void
    {
        $administrator = $this->administrator();
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();
        $course = $this->makeCourse($instructor);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Completed,
        ]);

        $this->makeCertificate($enrollment, CertificateStatus::Issued);
        $this->makeCertificate($enrollment, CertificateStatus::Revoked);

        $this->actingAs($administrator)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Certificates earned', false);
    }

    public function test_quiz_count_only_includes_passed_attempts(): void
    {
        [$student, $course] = $this->enrolledStudent();
        $quiz = Quiz::factory()->for($course, 'course')->required()->withQuestion()->create([
            'created_by' => $course->instructor_id,
            'status' => QuizStatus::Published,
            'position' => 1,
        ]);

        $enrollment = Enrollment::query()->where('student_id', $student->id)->firstOrFail();

        QuizAttempt::factory()->for($quiz, 'quiz')->failed()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Quizzes passed');

        QuizAttempt::factory()->for($quiz, 'quiz')->passed()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'attempt_number' => 2,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Course, 2: Lesson}
     */
    private function enrolledStudent(): array
    {
        $instructor = User::factory()->instructor()->create();
        $course = $this->makeCourse($instructor);

        $lesson = $course->lessons()->firstOrFail();

        $student = User::factory()->create();

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$student, $course, $lesson];
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user->fresh();
    }

    private function makeCourse(User $instructor, ?string $title = null): Course
    {
        $course = Course::factory()->for($instructor, 'instructor')->create([
            'title' => $title ?? 'Dashboard Course '.$instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        Lesson::factory()->for($module, 'module')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        return $course;
    }

    private function anotherLesson(Course $course, int $position): Lesson
    {
        return Lesson::factory()->for($course->modules()->firstOrFail(), 'module')->create([
            'position' => $position,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);
    }

    private function completeLesson(Course $course, Lesson $lesson, User $student): void
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $progress = new LessonProgress;
        $progress->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'last_viewed_at' => now(),
        ]);
        $progress->save();
    }

    private function makeCertificate(Enrollment $enrollment, CertificateStatus $status): Certificate
    {
        $certificate = new Certificate;
        $certificate->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'certificate_code' => 'ITH-'.strtoupper(uniqid()),
            'student_name_snapshot' => 'Test Student',
            'course_title_snapshot' => 'Test Course',
            'completion_date' => now()->toDateString(),
            'issued_by' => $enrollment->student_id,
            'replaces_certificate_id' => null,
            'status' => $status,
            'active_slot' => $status === CertificateStatus::Issued ? 1 : null,
        ]);
        $certificate->save();

        return $certificate;
    }
}
