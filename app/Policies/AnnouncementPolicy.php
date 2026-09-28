<?php

namespace App\Policies;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;

/**
 * Who may publish an announcement, and who may read one.
 *
 * Two answers, and they come from two different places on purpose.
 *
 * Publishing is a role question, because publishing into a course is the
 * instructor's own decision about their own course and publishing to the whole
 * platform is administration's. Neither is derivable from the row.
 *
 * Reading is an enrollment question, and it is answered by the same query the
 * list is built from, so a hand-typed id cannot reach a page the list would not
 * have shown. Announcement::scopeVisibleTo is that query; this method asks the
 * same question about one row.
 */
class AnnouncementPolicy
{
    public function view(User $actor, Announcement $announcement): bool
    {
        if (! $this->isActive($actor)) {
            return false;
        }

        // The author's own course, or the platform, is readable to them so a
        // person can see what they published. A student has no such claim and is
        // answered by the enrollment instead.
        if ($announcement->author_id === $actor->id) {
            return true;
        }

        if (! $announcement->isCourseScoped()) {
            return true;
        }

        $course = $announcement->course;

        if ($course === null || $course->status !== CourseStatus::Published) {
            return false;
        }

        // An instructor of that course may read what was said about it, which is
        // the other half of "own courses only".
        if ($actor->profile?->role === UserRole::Instructor && $course->instructor_id === $actor->id) {
            return true;
        }

        return $course->enrollments()
            ->where('student_id', $actor->id)
            ->whereIn('status', [
                EnrollmentStatus::Active,
                EnrollmentStatus::Completed,
            ])
            ->exists();
    }

    /**
     * Publishing into one course.
     *
     * An administrator is refused as well as a student, and that is deliberate:
     * administration is not teaching, and letting a platform administrator speak
     * into somebody's course would be the wrong kind of power.
     */
    public function createCourse(User $actor, Course $course): bool
    {
        return $this->isActive($actor)
            && $actor->profile?->role === UserRole::Instructor
            && $course->instructor_id === $actor->id;
    }

    /**
     * Publishing to everybody.
     */
    public function createPlatform(User $actor): bool
    {
        return $this->isActive($actor)
            && $actor->profile?->role === UserRole::Administrator;
    }

    /**
     * Withdrawing.
     *
     * Only the author. An administrator may close a support thread because a
     * support thread is a queue; an announcement is a statement somebody made in
     * their own name, and an administrator taking it down is a different act
     * that this system does not have.
     */
    public function delete(User $actor, Announcement $announcement): bool
    {
        return $this->isActive($actor)
            && $announcement->author_id === $actor->id;
    }

    public function viewAny(User $actor): bool
    {
        return $this->isActive($actor);
    }

    private function isActive(User $actor): bool
    {
        return $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
