<?php

namespace App\Providers;

use App\Events\AnnouncementPublished;
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
use App\Listeners\Notifications\NotifyRecipientsOfAnnouncement;
use App\Listeners\Notifications\NotifyStudentOfCertificateChange;
use App\Listeners\Notifications\NotifyStudentOfCourseCompletion;
use App\Listeners\Notifications\NotifyStudentOfQuizResult;
use Illuminate\Support\ServiceProvider;

/**
 * Which listener answers which event.
 *
 * Written out rather than inferred from the type hints, for two reasons. The
 * automatic discovery only finds a listener whose method is named `handle`, so
 * the three listeners that answer two events each would have needed a `handle`
 * per event and a union parameter, and a union parameter is how a listener ends
 * up reading the wrong property. And the map is the answer to "what happens when
 * a student finishes a quiz", which should be readable in one place rather than
 * assembled from a filename convention.
 *
 * Note that a student hears about their own result and an instructor hears about
 * the attempt. Two listeners, two recipients, from one event, so the two notices
 * cannot disagree about whether the quiz was passed.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, array{0: class-string, 1: string}>>
     */
    protected array $listen = [
        AnnouncementPublished::class => [
            [NotifyRecipientsOfAnnouncement::class, 'handle'],
        ],

        StudentEnrolled::class => [
            [NotifyInstructorOfEnrollment::class, 'handle'],
        ],

        LessonStarted::class => [
            [NotifyInstructorOfLessonActivity::class, 'handleStarted'],
        ],

        LessonCompleted::class => [
            [NotifyInstructorOfLessonActivity::class, 'handleCompleted'],
        ],

        QuizStarted::class => [
            [NotifyInstructorOfQuizActivity::class, 'handleStarted'],
        ],

        QuizGraded::class => [
            [NotifyInstructorOfQuizActivity::class, 'handleGraded'],
            [NotifyStudentOfQuizResult::class, 'handle'],
        ],

        CourseCompleted::class => [
            [NotifyStudentOfCourseCompletion::class, 'handle'],
        ],

        CertificateRevoked::class => [
            [NotifyStudentOfCertificateChange::class, 'handleRevoked'],
        ],

        CertificateReissued::class => [
            [NotifyStudentOfCertificateChange::class, 'handleReissued'],
        ],

        ContentPublished::class => [
            [NotifyEnrolledStudentsOfContent::class, 'handle'],
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
