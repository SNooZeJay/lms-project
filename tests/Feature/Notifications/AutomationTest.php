<?php

namespace Tests\Feature\Notifications;

use App\Actions\Courses\PublishCourse;
use App\Actions\Enrollment\EnrollStudent;
use App\Actions\Learning\MarkLessonComplete;
use App\Actions\Learning\RecordLessonActivity;
use App\Actions\Quizzes\StartQuizAttempt;
use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationType;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * What the automation actually writes, and to whom.
 *
 * The specification's hardest requirements are all about who does and does not
 * hear about something, so almost every test here is about the recipient set
 * rather than about the sentence in the notice.
 *
 * Each test counts for one recipient, because the notifications table now has
 * several writers and a total across it would measure all of them.
 */
class AutomationTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $profile = []): User
    {
        $student = User::factory()->create();

        $student->profile->forceFill(array_merge([
            'role' => UserRole::Student,
            'account_status' => UserAccountStatus::Active,
        ], $profile))->save();

        return $student->fresh();
    }

    /**
     * @return array{0: Course, 1: User}
     */
    private function publishedCourse(int $maxAttempts = 3): array
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'status' => ContentStatus::Published,
        ]);

        Lesson::factory()->create([
            'module_id' => $module->id,
            'status' => ContentStatus::Published,
            'position' => 1,
        ]);

        return [$course, $instructor, $module];
    }

    private function noticesFor(User $user, NotificationType $type): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->count();
    }

    /* ------------------------------------------------------------ enrollment */

    public function test_enrolling_tells_the_instructor_and_nobody_else(): void
    {
        [$course, $instructor] = $this->publishedCourse();
        $student = $this->student();
        $bystander = $this->student();

        app(EnrollStudent::class)->handle($student, $course);

        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::CourseEnrollment));
        $this->assertSame(0, $this->noticesFor($student, NotificationType::CourseEnrollment));
        $this->assertSame(0, $this->noticesFor($bystander, NotificationType::CourseEnrollment));
    }

    public function test_enrolling_twice_tells_the_instructor_once(): void
    {
        [$course, $instructor] = $this->publishedCourse();
        $student = $this->student();

        app(EnrollStudent::class)->handle($student, $course);
        app(EnrollStudent::class)->handle($student, $course);

        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::CourseEnrollment));
    }

    /* --------------------------------------------------------------- lessons */

    public function test_a_student_starting_then_completing_a_lesson_tells_the_instructor_twice(): void
    {
        [$course, $instructor] = $this->publishedCourse();
        $student = $this->student();
        $enrollment = app(EnrollStudent::class)->handle($student, $course);
        $lesson = $course->lessons()->firstOrFail();

        // In this order, and the order matters. Completing a lesson writes the
        // progress row already completed, so a later "started" would find nothing
        // to move and would rightly announce nothing. Starting first is the
        // journey a student actually takes.
        app(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);
        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);

        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::LessonStarted));
        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::LessonCompleted));
    }

    public function test_completing_a_lesson_without_opening_it_first_announces_only_the_completion(): void
    {
        [$course, $instructor] = $this->publishedCourse();
        $student = $this->student();
        $enrollment = app(EnrollStudent::class)->handle($student, $course);
        $lesson = $course->lessons()->firstOrFail();

        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);
        app(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);

        $this->assertSame(
            1,
            $this->noticesFor($instructor, NotificationType::LessonCompleted),
            'The completion was not announced.'
        );

        $this->assertSame(
            0,
            $this->noticesFor($instructor, NotificationType::LessonStarted),
            'A lesson that was already complete was reported as newly started, because the row had nothing left to move from not started.'
        );
    }

    public function test_opening_the_same_lesson_repeatedly_tells_the_instructor_once(): void
    {
        [$course, $instructor] = $this->publishedCourse();
        $student = $this->student();
        $enrollment = app(EnrollStudent::class)->handle($student, $course);
        $lesson = $course->lessons()->firstOrFail();

        for ($i = 0; $i < 6; $i++) {
            app(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);
        }

        $this->assertSame(
            1,
            $this->noticesFor($instructor, NotificationType::LessonStarted),
            'Opening one lesson six times produced more than one notice, so the transition rule is not holding.'
        );
    }

    public function test_completing_the_same_lesson_twice_tells_the_instructor_once(): void
    {
        [$course, $instructor] = $this->publishedCourse();
        $student = $this->student();
        $enrollment = app(EnrollStudent::class)->handle($student, $course);
        $lesson = $course->lessons()->firstOrFail();

        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);
        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);

        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::LessonCompleted));
    }

    /* ----------------------------------------------------------------- quizzes */

    /**
     * A published quiz with one question and two options, plus the correct one.
     *
     * Built the way CreateQuizQuestion builds one, with forceFill, because the
     * models keep their columns out of $fillable on purpose. The first version
     * of this helper passed a question_type the Action never sets, and the
     * QuestionType enum has a single value, so there was nothing to pass.
     *
     * @return array{0: Quiz, 1: int} the correct option id
     */
    private function quizWithOneQuestion(Course $course, Module $module, int $maxAttempts = 3): array
    {
        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'lesson_id' => $course->lessons()->firstOrFail()->id,
            'status' => QuizStatus::Published,
            'max_attempts' => $maxAttempts,
            'passing_score_percent' => 50,
        ]);

        $question = new QuizQuestion;
        $question->forceFill([
            'quiz_id' => $quiz->id,
            'prompt' => 'Which one?',
            'position' => 1,
            'points' => 1,
        ]);
        $question->save();

        $correct = new QuizOption;
        $correct->forceFill([
            'question_id' => $question->id,
            'option_text' => 'This one',
            'position' => 1,
            'is_correct' => true,
        ]);
        $correct->save();

        $wrong = new QuizOption;
        $wrong->forceFill([
            'question_id' => $question->id,
            'option_text' => 'Not this one',
            'position' => 2,
            'is_correct' => false,
        ]);
        $wrong->save();

        return [$quiz, $correct->id];
    }

    public function test_starting_a_quiz_tells_the_instructor(): void
    {
        [$course, $instructor, $module] = $this->publishedCourse();
        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $course);
        [$quiz] = $this->quizWithOneQuestion($course, $module);

        app(StartQuizAttempt::class)->handle($student, $quiz);

        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::QuizStarted));
    }

    public function test_passing_a_quiz_tells_the_student_and_not_the_instructor_of_a_pass(): void
    {
        [$course, $instructor, $module] = $this->publishedCourse();
        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $course);
        [$quiz, $correct] = $this->quizWithOneQuestion($course, $module);

        $attempt = app(StartQuizAttempt::class)->handle($student, $quiz);
        app(SubmitQuizAttempt::class)->handle($student, $quiz, $attempt, [$quiz->questions()->firstOrFail()->id => $correct]);

        $this->assertSame(1, $this->noticesFor($student, NotificationType::QuizPassed));
        $this->assertSame(0, $this->noticesFor($student, NotificationType::QuizFailed));
        $this->assertSame(0, $this->noticesFor($student, NotificationType::RetakeRequired));
        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::QuizCompleted));
    }

    public function test_failing_with_attempts_left_offers_a_retake(): void
    {
        [$course, $instructor, $module] = $this->publishedCourse();
        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $course);
        [$quiz, $correct] = $this->quizWithOneQuestion($course, $module, maxAttempts: 3);

        $attempt = app(StartQuizAttempt::class)->handle($student, $quiz);

        // The wrong option, so the attempt fails.
        $wrong = $quiz->questions()->firstOrFail()->options()->where('is_correct', false)->firstOrFail();
        app(SubmitQuizAttempt::class)->handle($student, $quiz, $attempt, [$quiz->questions()->firstOrFail()->id => $wrong->id]);

        $this->assertSame(1, $this->noticesFor($student, NotificationType::QuizFailed));
        $this->assertSame(
            1,
            $this->noticesFor($student, NotificationType::RetakeRequired),
            'A student who failed with attempts left was not offered one, which is a dead end.'
        );
    }

    public function test_failing_with_no_attempts_left_offers_nothing(): void
    {
        [$course, $instructor, $module] = $this->publishedCourse();
        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $course);

        // One attempt only, and it is about to be used and failed.
        [$quiz] = $this->quizWithOneQuestion($course, $module, maxAttempts: 1);

        $attempt = app(StartQuizAttempt::class)->handle($student, $quiz);
        $wrong = $quiz->questions()->firstOrFail()->options()->where('is_correct', false)->firstOrFail();
        app(SubmitQuizAttempt::class)->handle($student, $quiz, $attempt, [$quiz->questions()->firstOrFail()->id => $wrong->id]);

        $this->assertSame(1, $this->noticesFor($student, NotificationType::QuizFailed));
        $this->assertSame(
            0,
            $this->noticesFor($student, NotificationType::RetakeRequired),
            'A student with no attempts left was offered a retake, which the quiz will then refuse.'
        );
    }

    public function test_the_retake_offer_reads_the_quiz_limit_rather_than_a_constant(): void
    {
        /*
         | The same failure, against two quizzes configured differently.
         |
         | This is the specification's "do not hardcode arbitrary passing
         | requirements" applied to attempts. A listener that counted down from a
         | constant would pass the test above, where the limit happens to be
         | three, and fail here. The number has to come from the quiz.
         */
        foreach ([1, 3] as $limit) {
            [$course, $instructor, $module] = $this->publishedCourse();
            $student = $this->student();
            app(EnrollStudent::class)->handle($student, $course);
            [$quiz] = $this->quizWithOneQuestion($course, $module, maxAttempts: $limit);

            $attempt = app(StartQuizAttempt::class)->handle($student, $quiz);
            $wrong = $quiz->questions()->firstOrFail()->options()->where('is_correct', false)->firstOrFail();
            app(SubmitQuizAttempt::class)->handle($student, $quiz, $attempt, [$quiz->questions()->firstOrFail()->id => $wrong->id]);

            $this->assertSame(
                $limit > 1 ? 1 : 0,
                $this->noticesFor($student, NotificationType::RetakeRequired),
                "A quiz limited to {$limit} attempt(s) offered the wrong number of retakes."
            );
        }
    }

    public function test_submitting_the_same_attempt_twice_announces_it_once(): void
    {
        [$course, $instructor, $module] = $this->publishedCourse();
        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $course);
        [$quiz, $correct] = $this->quizWithOneQuestion($course, $module);

        $attempt = app(StartQuizAttempt::class)->handle($student, $quiz);
        $question = $quiz->questions()->firstOrFail();

        app(SubmitQuizAttempt::class)->handle($student, $quiz, $attempt, [$question->id => $correct]);

        // The Action refuses a status that is no longer in progress, which is the
        // primary guard. The dedup key is the backstop.
        try {
            app(SubmitQuizAttempt::class)->handle($student, $quiz, $attempt, [$question->id => $correct]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::QuizCompleted));
        $this->assertSame(1, $this->noticesFor($student, NotificationType::QuizPassed));
    }

    /* -------------------------------------------------------- content publish */

    public function test_publishing_a_course_tells_every_enrolled_student_and_nobody_else(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Draft,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create(['course_id' => $course->id, 'status' => ContentStatus::Draft]);
        Lesson::factory()->create(['module_id' => $module->id, 'status' => ContentStatus::Draft, 'position' => 1]);

        $active = $this->student();
        $cancelled = $this->student();
        $suspended = $this->student(['account_status' => UserAccountStatus::Suspended]);
        $notEnrolled = $this->student();
        $otherInstructor = User::factory()->instructor()->create();

        foreach ([$active, $cancelled, $suspended] as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => $student === $cancelled ? EnrollmentStatus::Cancelled : EnrollmentStatus::Active,
            ]);
        }

        app(PublishCourse::class)->handle($instructor, $course);

        $this->assertSame(1, $this->noticesFor($active, NotificationType::CourseContentPublished));
        $this->assertSame(0, $this->noticesFor($cancelled, NotificationType::CourseContentPublished));
        $this->assertSame(0, $this->noticesFor($suspended, NotificationType::CourseContentPublished));
        $this->assertSame(0, $this->noticesFor($notEnrolled, NotificationType::CourseContentPublished));
        $this->assertSame(0, $this->noticesFor($otherInstructor, NotificationType::CourseContentPublished));
    }

    public function test_a_cancelled_enrollment_is_told_nothing_even_while_it_is_being_cancelled(): void
    {
        /*
         | The specification's "not unenrolled, removed, expired or unauthorized
         | users", tested at the moment that matters.
         |
         | The recipient set is resolved when the event fires, not when the notice
         | is read, so a cancellation that landed a moment earlier is already
         | reflected. A suspended account is the same case for a different reason
         | and is covered above.
         */
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Draft,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create(['course_id' => $course->id, 'status' => ContentStatus::Draft]);
        Lesson::factory()->create(['module_id' => $module->id, 'status' => ContentStatus::Draft, 'position' => 1]);

        $student = $this->student();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $enrollment->forceFill(['status' => EnrollmentStatus::Cancelled])->save();

        app(PublishCourse::class)->handle($instructor, $course);

        $this->assertSame(0, $this->noticesFor($student, NotificationType::CourseContentPublished));
    }

    public function test_the_notice_link_is_one_the_recipient_can_actually_open(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Draft,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create(['course_id' => $course->id, 'status' => ContentStatus::Draft]);
        Lesson::factory()->create(['module_id' => $module->id, 'status' => ContentStatus::Draft, 'position' => 1]);

        $student = $this->student();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        app(PublishCourse::class)->handle($instructor, $course);

        $notice = Notification::query()
            ->where('user_id', $student->id)
            ->where('type', NotificationType::CourseContentPublished)
            ->firstOrFail();

        $this->assertNotNull($notice->link, 'The notice was stored with no link.');

        $this->actingAs($student)->get($notice->link)->assertOk();
    }
}
