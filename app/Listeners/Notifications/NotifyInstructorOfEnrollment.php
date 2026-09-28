<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Events\StudentEnrolled;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tells the instructor that somebody joined their course.
 *
 * Fired from the path that genuinely created the enrollment. The Action's reuse
 * branch, which handles a second request for a course the student already holds,
 * dispatches nothing, so this cannot tell an instructor that somebody joined
 * last week.
 */
class NotifyInstructorOfEnrollment
{
    public function __construct(private readonly RecordNotification $notify) {}

    public function handle(StudentEnrolled $event): void
    {
        $enrollment = $event->enrollment;
        $course = $enrollment->course;
        $instructor = $course->instructor;

        if ($instructor === null) {
            return;
        }

        $student = $enrollment->student;

        $this->notify->handle(
            $instructor,
            NotificationType::CourseEnrollment,
            "{$student->name} enrolled",
            "{$student->name} enrolled in {$course->title}.",
            course: $course,
            /*
             | Keyed on the enrollment, not on the pair. The unique rule on
             | (student_id, course_id) already means one student is in a course
             | once, so this key and the enrollment id are the same fact, and a
             | second request cannot produce a second row either way.
             */
            dedupKey: "enrollment:{$enrollment->id}",
            link: route('instructor.courses.show', $course),
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('view', $course),
            subjectType: 'enrollment',
            subjectId: $enrollment->id,
        );
    }
}
