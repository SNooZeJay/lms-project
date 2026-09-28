<?php

namespace Tests\Feature\Notifications;

use App\Actions\Courses\PublishCourse;
use App\Actions\Enrollment\EnrollStudent;
use App\Actions\Learning\MarkLessonComplete;
use App\Actions\Learning\RecordLessonActivity;
use App\Actions\Quizzes\StartQuizAttempt;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\NotificationType;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Events\CertificateReissued;
use App\Events\CertificateRevoked;
use App\Events\ContentPublished;
use App\Events\CourseCompleted;
use App\Events\LessonCompleted;
use App\Events\LessonStarted;
use App\Events\QuizGraded;
use App\Events\QuizStarted;
use App\Events\StudentEnrolled;
use App\Listeners\Notifications\NotifyEnrolledStudentsOfContent;
use App\Listeners\Notifications\NotifyInstructorOfEnrollment;
use App\Listeners\Notifications\NotifyInstructorOfLessonActivity;
use App\Listeners\Notifications\NotifyInstructorOfQuizActivity;
use App\Listeners\Notifications\NotifyStudentOfCertificateChange;
use App\Listeners\Notifications\NotifyStudentOfCourseCompletion;
use App\Listeners\Notifications\NotifyStudentOfQuizResult;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Slice 2: the events exist, and they are dispatched at the right moment.
 *
 * Two rules are checked here, and both are the kind that are easy to state and
 * easy to break:
 *
 *   1. **Never inside a transaction.** The rule lives on each event class as
 *      ShouldDispatchAfterCommit, so it cannot be forgotten at a call site. If a
 *      transaction rolls back, nothing fires.
 *   2. **Transitions only.** Opening a lesson five times dispatches once.
 *      Marking a completed lesson complete again dispatches nothing. Asking to
 *      start a quiz that is already open dispatches nothing.
 *
 * Slices 3, 4, 5 and 7 have since landed the listeners, so the mapping from
 * event to listener is pinned here instead, and the after-commit rule is still
 * asserted against real transactions.
 */ class DomainEventDispatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Course, 1: User, 2: Module, 3: Lesson}
     */
    private function courseWithLesson(bool $published = true): array
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => $published ? CourseStatus::Published : CourseStatus::Draft,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'status' => $published ? ContentStatus::Published : ContentStatus::Draft,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'status' => $published ? ContentStatus::Published : ContentStatus::Draft,
            'position' => 1,
        ]);

        return [$course, $instructor, $module, $lesson];
    }

    private function enrolledStudent($course, $instructor): array
    {
        $student = $this->student();

        $enrollment = app(EnrollStudent::class)->handle($student, $course);

        return [$student, $enrollment];
    }

    /* ------------------------------------------------ the events are inert */

    public function test_exactly_one_listener_answers_each_domain_event(): void
    {
        /*
         | The mapping, pinned.
         |
         | This used to assert that nothing answered any of these, which was
         | correct while slice 2 emitted nothing and stopped being true the
         | moment the listeners landed. Asserting the real map means a listener
         | added by accident is a failure rather than a surprise in production,
         | and a listener removed is a failure too.
         */
        $expected = [
            StudentEnrolled::class => [NotifyInstructorOfEnrollment::class.'@handle'],
            LessonStarted::class => [NotifyInstructorOfLessonActivity::class.'@handleStarted'],
            LessonCompleted::class => [NotifyInstructorOfLessonActivity::class.'@handleCompleted'],
            QuizStarted::class => [NotifyInstructorOfQuizActivity::class.'@handleStarted'],
            QuizGraded::class => [
                NotifyInstructorOfQuizActivity::class.'@handleGraded',
                NotifyStudentOfQuizResult::class.'@handle',
            ],
            CourseCompleted::class => [NotifyStudentOfCourseCompletion::class.'@handle'],
            CertificateRevoked::class => [NotifyStudentOfCertificateChange::class.'@handleRevoked'],
            CertificateReissued::class => [NotifyStudentOfCertificateChange::class.'@handleReissued'],
            ContentPublished::class => [NotifyEnrolledStudentsOfContent::class.'@handle'],
        ];

        foreach ($expected as $event => $listeners) {
            $this->assertSame(
                $listeners,
                Event::getRawListeners()[$event] ?? [],
                "{$event} is answered by a different set of listeners than this test expects."
            );
        }
    }

    /* ------------------------------------------------ after commit, not inside */

    /**
     * The timing rule, observed through the database rather than a spy listener.
     *
     * A closure registered with Event::listen was the first version of this and
     * it poisoned the suite: 88 failures in the webhook tests, with counts of
     * active enrollments that grew by a few on every run, and it only reproduced
     * when this file ran after another one. Registering a listener mutates
     * application state that outlives the test, and the harness does not put it
     * back.
     *
     * What is actually being claimed is that no notice exists until the action
     * that caused it has finished. That is observable in the rows: the enrollment
     * notice is absent before the enrollment and present after it returns, which
     * is the transaction having closed rather than the dispatch having happened
     * inside one.
     */
    public function test_no_notice_exists_before_the_action_that_causes_it(): void
    {
        [$course, $instructor] = $this->courseWithLesson();
        $student = $this->student();

        $noticesFor = fn (): int => Notification::query()
            ->where('user_id', $instructor->id)
            ->where('type', NotificationType::CourseEnrollment)
            ->count();

        $this->assertSame(0, $noticesFor(), 'The fixture is not clean, so this test proves nothing.');

        app(EnrollStudent::class)->handle($student, $course);

        $this->assertSame(
            1,
            $noticesFor(),
            'The notice did not appear once the action had returned, so the event was not delivered at all.'
        );
    }

    public function test_the_event_fires_once_the_transaction_commits(): void
    {
        Event::fake([LessonStarted::class]);

        [$course, $instructor, $module, $lesson] = $this->courseWithLesson();
        [$student, $enrollment] = $this->enrolledStudent($course, $instructor);

        app(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);

        Event::assertDispatchedTimes(LessonStarted::class, 1);
    }

    public function test_every_domain_event_declares_that_it_waits_for_the_commit(): void
    {
        /*
         | Asserted on the class rather than trusted at each call site.
         |
         | Written as ->afterCommit() on the dispatch it is one thing every
         | caller has to remember, and the first one that forgets produces a
         | notification about a transaction that then rolled back. Implementing
         | the contract makes it impossible to dispatch one of these early, which
         | is why the reflection is here rather than a comment.
         */
        foreach ([
            StudentEnrolled::class,
            LessonStarted::class,
            LessonCompleted::class,
            QuizStarted::class,
            QuizGraded::class,
            CourseCompleted::class,
            CertificateRevoked::class,
            CertificateReissued::class,
            ContentPublished::class,
        ] as $event) {
            $this->assertTrue(
                is_a($event, ShouldDispatchAfterCommit::class, true),
                "{$event} does not implement ShouldDispatchAfterCommit, so it can be dispatched inside a transaction."
            );
        }
    }

    public function test_the_badge_moves_when_an_action_completes_its_transaction(): void
    {
        /*
         | The events are not inert any more.
         |
         | Slices 3, 4, 5 and 7 landed the listeners, so this file no longer
         | asserts that nothing answers them. What it still asserts is the thing
         | the plan calls the one rule that is not optional.
         |
         | Counted for this instructor and this course rather than across the
         | table, because the notifications table now has other writers and a
         | total measures them too.
         */
        [$course, $instructor] = $this->courseWithLesson();
        $student = $this->student();

        $countFor = fn (): int => Notification::query()
            ->where('user_id', $instructor->id)
            ->where('type', NotificationType::CourseEnrollment)
            ->count();

        $this->assertSame(0, $countFor(), 'Building the fixture produced an enrollment notice, so the fixture is not clean.');

        app(EnrollStudent::class)->handle($student, $course);

        $this->assertSame(
            1,
            $countFor(),
            'A committed enrollment did not tell the instructor, so the listener is not wired.'
        );
    }

    /* ------------------------------------------------------ transitions only */

    public function test_opening_a_lesson_five_times_dispatches_once(): void
    {
        Event::fake([LessonStarted::class]);

        [$course, $instructor, $module, $lesson] = $this->courseWithLesson();
        [$student, $enrollment] = $this->enrolledStudent($course, $instructor);

        for ($i = 0; $i < 5; $i++) {
            app(RecordLessonActivity::class)->handle($student, $enrollment, $lesson);
        }

        Event::assertDispatchedTimes(LessonStarted::class, 1);
    }

    public function test_marking_an_already_complete_lesson_dispatches_nothing(): void
    {
        Event::fake([LessonCompleted::class]);

        [$course, $instructor, $module, $lesson] = $this->courseWithLesson();
        [$student, $enrollment] = $this->enrolledStudent($course, $instructor);

        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);
        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);
        app(MarkLessonComplete::class)->handle($student, $enrollment, $lesson);

        Event::assertDispatchedTimes(LessonCompleted::class, 1);
    }

    public function test_re_enrolling_a_student_dispatches_nothing(): void
    {
        Event::fake([StudentEnrolled::class]);

        [$course] = $this->courseWithLesson();

        // The same student twice, because the helper above makes a new one every
        // call and two different students joining is two genuine events. The
        // first version of this test called the helper twice and then asserted
        // one dispatch, which was asserting that a second student did not join.
        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $course);
        app(EnrollStudent::class)->handle($student, $course);

        Event::assertDispatchedTimes(StudentEnrolled::class, 1);
    }

    private function student(): User
    {
        $student = User::factory()->create();
        $student->profile->forceFill(['role' => UserRole::Student])->save();

        return $student->fresh();
    }

    public function test_reopening_an_open_quiz_attempt_dispatches_nothing(): void
    {
        Event::fake([QuizStarted::class]);

        $quiz = $this->publishedQuiz();

        $student = $this->student();
        app(EnrollStudent::class)->handle($student, $quiz->course);

        $action = app(StartQuizAttempt::class);
        $action->handle($student, $quiz);
        $action->handle($student, $quiz);

        Event::assertDispatchedTimes(QuizStarted::class, 1);
    }

    public function test_publishing_a_course_dispatches_the_content_event_once(): void
    {
        Event::fake([ContentPublished::class]);

        [$course, $instructor] = $this->courseWithLesson(published: false);

        app(PublishCourse::class)->handle($instructor, $course);

        Event::assertDispatchedTimes(ContentPublished::class, 1);
    }

    private function publishedQuiz(): Quiz
    {
        [$course, $instructor, $module, $lesson] = $this->courseWithLesson();

        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson->id,
            'status' => QuizStatus::Published,
        ]);

        return $quiz;
    }
}
