<?php

namespace Tests\Feature\Phase16;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
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
use App\Services\Reporting\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The dashboard analytics for the three roles.
 *
 * Every assertion is about a number that has to match a stored record, or about
 * a boundary that has to hold. Nothing is asserted against a value invented in
 * the test, so a failure always means the report and the database disagree.
 */
class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private OperationsReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->report = app(OperationsReport::class);
    }

    /* ---------------------------------------------------------------- student */

    public function test_student_totals_separate_completed_from_in_progress_courses(): void
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $this->enroll($student, $this->course($instructor), EnrollmentStatus::Active);
        $this->enroll($student, $this->course($instructor), EnrollmentStatus::Active);
        $this->enroll($student, $this->course($instructor), EnrollmentStatus::Completed);
        $this->enroll($student, $this->course($instructor), EnrollmentStatus::Cancelled);

        $stats = $this->report->forStudent($student);

        // A cancelled enrollment is in neither figure: it is neither being worked
        // on nor finished.
        $this->assertSame(3, $stats['courses_enrolled']);
        $this->assertSame(2, $stats['courses_in_progress']);
        $this->assertSame(1, $stats['courses_completed']);
    }

    public function test_student_average_progress_comes_from_the_real_lesson_totals(): void
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        // Two lessons, one complete: 50 percent.
        $first = $this->course($instructor, withLessons: 2);
        $firstEnrollment = $this->enroll($student, $first, EnrollmentStatus::Active);
        $this->completeLessons($student, $first->lessons->take(1), $firstEnrollment);

        // Four lessons, all complete: 100 percent.
        $second = $this->course($instructor, withLessons: 4);
        $secondEnrollment = $this->enroll($student, $second, EnrollmentStatus::Active);
        $this->completeLessons($student, $second->lessons, $secondEnrollment);

        $this->assertSame(75, $this->report->averageStudentProgress($student), 'The mean of 50 and 100 is 75.');
    }

    public function test_student_average_progress_is_zero_without_a_course(): void
    {
        $this->assertSame(0, $this->report->averageStudentProgress(User::factory()->create()));
    }

    public function test_student_pending_quizzes_count_only_reachable_unpassed_quizzes(): void
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();
        $course = $this->course($instructor);
        $this->enroll($student, $course, EnrollmentStatus::Active);

        $passed = $this->quiz($course);
        $this->quiz($course);
        $this->quiz($course, status: 'draft');

        // A quiz in a course the student never enrolled in must never count.
        $this->quiz($this->course($instructor));

        // Both published quizzes are outstanding at this point, because the pass
        // below has not been sat yet.
        $this->assertSame(2, $this->report->pendingQuizCount($student));

        QuizAttempt::factory()->passed()->create([
            'student_id' => $student->id,
            'quiz_id' => $passed->id,
        ]);

        $this->assertSame(1, $this->report->pendingQuizCount($student), 'One published, unpassed quiz is left.');
    }

    public function test_student_agenda_lists_real_dated_records_newest_first(): void
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();
        $course = $this->course($instructor);
        $enrollment = $this->enroll($student, $course, EnrollmentStatus::Active);

        $this->certificate($enrollment, $course, $student, now()->subDays(2)->toDateString());

        $agenda = $this->report->studentAgenda($student, 10);

        $this->assertNotEmpty($agenda);

        $previous = null;

        foreach ($agenda as $entry) {
            $this->assertArrayHasKey('at', $entry);
            $this->assertArrayHasKey('label', $entry);
            $this->assertStringContainsString($course->title, $entry['label']);

            // Every source column records something that already happened, so an
            // entry dated in the future would mean a fabricated event.
            $this->assertTrue($entry['at']->lessThanOrEqualTo(now()->addMinute()));

            if ($previous !== null) {
                $this->assertTrue($entry['at']->lessThanOrEqualTo($previous), 'The agenda runs newest first.');
            }

            $previous = $entry['at'];
        }
    }

    public function test_student_agenda_is_empty_for_a_new_account(): void
    {
        $this->assertSame([], $this->report->studentAgenda(User::factory()->create(), 10)->all());
    }

    /* ------------------------------------------------------------- instructor */

    public function test_instructor_totals_count_drafts_and_active_students(): void
    {
        $instructor = User::factory()->instructor()->create();
        $other = User::factory()->instructor()->create();

        $published = $this->course($instructor, status: CourseStatus::Published);
        $this->course($instructor, status: CourseStatus::Draft);
        $this->course($other);

        $this->enroll(User::factory()->create(), $published, EnrollmentStatus::Active);
        $this->enroll(User::factory()->create(), $published, EnrollmentStatus::Active);
        $this->enroll(User::factory()->create(), $published, EnrollmentStatus::Completed);

        $stats = $this->report->forInstructor($instructor);

        $this->assertSame(2, $stats['courses'], 'Only the two owned courses count.');
        $this->assertSame(1, $stats['published_courses']);
        $this->assertSame(1, $stats['draft_courses']);
        $this->assertSame(2, $stats['active_students'], 'Distinct students holding a live enrollment.');
    }

    public function test_instructor_completion_rate_is_a_percentage_of_real_enrollments(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = $this->course($instructor);

        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Completed);
        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Completed);
        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Active);
        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Cancelled);

        // Two completed out of the three that ever started. A cancelled
        // enrollment is excluded because that student never began.
        $this->assertSame(67, $this->report->forInstructor($instructor)['completion_rate']);
    }

    public function test_instructor_completion_rate_is_zero_without_enrollments(): void
    {
        $instructor = User::factory()->instructor()->create();
        $this->course($instructor);

        $this->assertSame(0, $this->report->forInstructor($instructor)['completion_rate']);
    }

    public function test_instructor_agenda_covers_only_their_own_courses(): void
    {
        $instructor = User::factory()->instructor()->create();
        $stranger = User::factory()->instructor()->create();

        $mine = $this->course($instructor);
        $theirs = $this->course($stranger);

        $this->enroll(User::factory()->create(), $mine, EnrollmentStatus::Active);
        $this->enroll(User::factory()->create(), $theirs, EnrollmentStatus::Active);

        $agenda = $this->report->instructorAgenda($instructor, 20);

        $this->assertNotEmpty($agenda);

        foreach ($agenda as $entry) {
            $this->assertStringNotContainsString($theirs->title, $entry['label']);
        }
    }

    /* ---------------------------------------------------------- administrator */

    public function test_administrator_totals_add_role_and_course_state_counts(): void
    {
        User::factory()->count(2)->create();
        User::factory()->instructor()->create();

        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $instructor = User::factory()->instructor()->create();
        $published = $this->course($instructor, status: CourseStatus::Published);
        $this->course($instructor, status: CourseStatus::Draft);

        $this->enroll(User::factory()->create(), $published, EnrollmentStatus::Completed);

        $stats = $this->report->forAdministrator();

        $this->assertSame(1, $stats['administrators']);
        $this->assertSame(1, $stats['published_courses']);
        $this->assertSame(1, $stats['completed_enrollments']);
    }

    public function test_enrollment_status_counts_cover_every_state_and_add_up(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = $this->course($instructor);

        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Active);
        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Completed);
        $this->enroll(User::factory()->create(), $course, EnrollmentStatus::Cancelled);

        $counts = $this->report->enrollmentStatusCounts();

        $this->assertSame(1, $counts['active']);
        $this->assertSame(1, $counts['completed']);
        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame(0, $counts['pending_payment']);
        $this->assertSame(3, array_sum($counts), 'Every enrollment lands in exactly one state.');
    }

    public function test_administrator_agenda_reports_real_payments(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = $this->course($instructor);
        $student = User::factory()->create();
        $enrollment = $this->enroll($student, $course, EnrollmentStatus::Active);

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount_minor' => 100000,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid,
            'idempotency_key' => 'audit-'.uniqid(),
            'paid_at' => now()->subDay(),
        ]);
        $payment->save();

        $agenda = $this->report->administratorAgenda(20);

        $this->assertNotEmpty($agenda);
        $this->assertStringContainsString($course->title, $agenda->pluck('label')->implode(' '));
    }

    /* ------------------------------------------------------------- boundaries */

    public function test_a_student_never_sees_instructor_or_administrator_figures(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('completion_rate')
            ->assertDontSee('paid_payments')
            ->assertDontSee('Active enrollments');
    }

    public function test_the_instructor_completion_rate_is_not_rendered_on_the_student_page(): void
    {
        $instructor = User::factory()->instructor()->create();
        $this->enroll(User::factory()->create(), $this->course($instructor), EnrollmentStatus::Completed);

        $rate = (string) $this->report->forInstructor($instructor)['completion_rate'];

        $body = (string) $this->actingAs(User::factory()->create())
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('completion_rate', $body);
        $this->assertStringNotContainsString($rate.'%', $body);
    }

    /* --------------------------------------------------------------- fixtures */

    private function course(
        User $instructor,
        ?string $title = null,
        CourseStatus $status = CourseStatus::Published,
        int $withLessons = 0
    ): Course {
        $course = Course::factory()->for($instructor, 'instructor')->create([
            'title' => $title ?? 'Analytics Course '.uniqid(),
            'status' => $status,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        if ($withLessons > 0) {
            $module = Module::factory()->for($course, 'course')->create([
                'position' => 1,
                'status' => ContentStatus::Published,
            ]);

            foreach (range(1, $withLessons) as $position) {
                Lesson::factory()->for($module, 'module')->create([
                    'position' => $position,
                    'status' => ContentStatus::Published,
                    'is_required' => true,
                ]);
            }
        }

        return $course->fresh('lessons');
    }

    /**
     * The factory states are used rather than a bare create, because they are
     * what write the real lifecycle timestamps. An enrollment built by hand has
     * a null activated_at, which would leave the agenda silently empty instead
     * of testing anything.
     */
    private function enroll(User $student, Course $course, EnrollmentStatus $status): Enrollment
    {
        $state = match ($status) {
            EnrollmentStatus::Active => Enrollment::factory()->active(),
            EnrollmentStatus::Completed => Enrollment::factory()->completed(),
            EnrollmentStatus::Cancelled => Enrollment::factory()->cancelled(),
            default => Enrollment::factory(),
        };

        return $state->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    private function quiz(Course $course, string $status = 'published', ?int $position = null): Quiz
    {
        return Quiz::factory()->create([
            'course_id' => $course->id,
            'status' => $status,
            // Position is unique within a course, and the factory always writes 1.
            'position' => $position ?? ($course->quizzes()->count() + 1),
        ]);
    }

    /**
     * @param  Collection<int, Lesson>  $lessons
     */
    private function completeLessons(User $student, $lessons, Enrollment $enrollment): void
    {
        foreach ($lessons as $lesson) {
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
    }

    private function certificate(Enrollment $enrollment, Course $course, User $student, string $when): Certificate
    {
        $certificate = new Certificate;
        $certificate->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'certificate_code' => 'ITH-'.strtoupper(uniqid()),
            'student_name_snapshot' => $student->name,
            'course_title_snapshot' => $course->title,
            'completion_date' => $when,
            'issued_by' => $student->id,
            'replaces_certificate_id' => null,
            'status' => CertificateStatus::Issued,
            'active_slot' => 1,
        ]);
        $certificate->save();

        return $certificate;
    }
}
