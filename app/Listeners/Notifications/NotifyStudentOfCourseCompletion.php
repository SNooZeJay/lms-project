<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Events\CourseCompleted;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tells a student they finished a course, and that a certificate exists.
 *
 * Both come from one event because the Action does both in one transaction.
 * Splitting them would let a listener be told about a completion with no
 * certificate, which is the state the plan rules out as not separately
 * observable.
 *
 * The certificate is optional. A course whose requirements are met but which
 * does not issue certificates completes without one, and that is a real outcome
 * rather than a failure, so the notice says the course is done and stops there
 * instead of promising a document that does not exist.
 */
class NotifyStudentOfCourseCompletion
{
    public function __construct(private readonly RecordNotification $notify) {}

    public function handle(CourseCompleted $event): void
    {
        $student = $event->enrollment->student;
        $course = $event->course;

        $this->notify->handle(
            $student,
            NotificationType::CourseCompleted,
            'You completed '.$course->title,
            "You finished every requirement for \"{$course->title}\".",
            course: $course,
            dedupKey: "enrollment:{$event->enrollment->id}",
            link: route('student.courses.show', $course),
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('viewCourseForStudent', $course),
            subjectType: 'enrollment',
            subjectId: $event->enrollment->id,
        );

        if ($event->certificateId === null) {
            return;
        }

        $certificate = Certificate::query()->find($event->certificateId);

        if ($certificate === null) {
            return;
        }

        $this->notify->handle(
            $student,
            NotificationType::CertificateAvailable,
            'Your certificate is ready',
            "Your certificate for \"{$course->title}\" is ready to view.",
            course: $course,
            dedupKey: "certificate:{$certificate->id}",
            link: route('student.certificates.show', $certificate),
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('view', $certificate),
            subjectType: 'certificate',
            subjectId: $certificate->id,
        );
    }
}
