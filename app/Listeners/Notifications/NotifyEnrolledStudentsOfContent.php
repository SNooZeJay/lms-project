<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\ContentStatus;
use App\Enums\NotificationType;
use App\Events\ContentPublished;
use App\Models\Course;
use App\Models\User;
use App\Policies\LessonPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Tells the students of a course that something new is available.
 *
 * The recipient set comes from the batched policy query and nowhere else, which
 * is the whole design of this listener. Writing it as a loop over the course's
 * enrollments and a per-student access check would be correct and would cost one
 * query per student, which is the pattern the dashboard budget test exists to
 * fail.
 *
 * The batched query is the same composite the single check uses, including the
 * account status, so a suspended student is not in the set and a cancelled
 * enrollment is not in the set. A notice whose link answers 403 is worse than no
 * notice, because it tells somebody they have missed something.
 *
 * The dedup key carries the subject, so publishing a course twice does not
 * announce it twice and a course that later gains a subject announces that
 * subject separately.
 */
class NotifyEnrolledStudentsOfContent
{
    public function __construct(
        private readonly RecordNotification $notify,
        private readonly LessonPolicy $policy,
    ) {}

    public function handle(ContentPublished $event): void
    {
        $course = $event->course;

        // The event carries the status it fired with. A subject that has since
        // been archived produces no notice at all, because the notice would
        // describe something nobody can open.
        if ($event->status !== ContentStatus::Published) {
            return;
        }

        $studentIds = $this->policy->authorizedStudentIdsForCourse($course);

        if ($studentIds === []) {
            return;
        }

        $students = User::query()->whereIn('id', $studentIds)->get();

        $title = $this->titleFor($event);
        $body = $this->bodyFor($event, $course);
        $link = route('student.courses.show', $course);

        foreach ($students as $student) {
            $this->notify->handle(
                $student,
                NotificationType::CourseContentPublished,
                $title,
                $body,
                course: $course,
                dedupKey: $this->dedupKeyFor($event),
                link: $link,
                /*
                 | Re-checked per student even though the set already came from
                 | this same composite. The set was correct a moment ago; this is
                 | the rule the seam enforces for every notification, and a link
                 | nobody can open is dropped by the seam rather than stored.
                 */
                authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('viewCourseForStudent', $course),
                subjectType: 'course',
                subjectId: $course->id,
            );
        }
    }

    private function titleFor(ContentPublished $event): string
    {
        return 'New material in '.$event->course->title;
    }

    private function bodyFor(ContentPublished $event, Course $course): string
    {
        if ($event->isCourseItself()) {
            return "\"{$course->title}\" is now available. Everything in it has been published.";
        }

        return "\"{$event->subject->title}\" has been added to \"{$course->title}\".";
    }

    /**
     * The subject, so a later publication of something else announces on its own
     * and publishing the same course twice does not.
     */
    private function dedupKeyFor(ContentPublished $event): string
    {
        if ($event->isCourseItself()) {
            return "course:{$event->course->id}:published";
        }

        return "course:{$event->course->id}:subject:".class_basename($event->subject).":{$event->subject->getKey()}";
    }
}
