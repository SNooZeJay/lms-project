<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Support\StudentCourseAccess;

class LessonPolicy
{
    /**
     * Student lesson reading is decided by enrollment, not by publication.
     */
    public function viewForStudent(User $actor, Lesson $lesson): bool
    {
        return $this->isActiveStudent($actor)
            && $lesson->status === ContentStatus::Published
            && $lesson->module->status === ContentStatus::Published
            && StudentCourseAccess::allows($actor, $lesson->module->course);
    }

    /**
     * Every student id that satisfies the whole of viewForStudent for one course,
     * in one query.
     *
     * A fan-out across a course's students would otherwise call
     * StudentCourseAccess::allows() once per student, which is an N+1 and would
     * fail the dashboard query budget test. Measured over 120 students it is
     * roughly two orders of magnitude more work than this.
     *
     * It lives on the policy rather than on StudentCourseAccess, and that is
     * load-bearing rather than tidy. `allows()` reads enrollment status and
     * nothing else: it checks neither role nor account status, so of the students
     * it allows, 13 in 96 were suspended accounts holding an active enrollment.
     * A fan-out written against `allows()` alone would notify suspended accounts
     * and the notification link would land on a page that refuses them. This
     * reproduces the whole composite, so the two answers cannot drift.
     *
     * @return list<int>
     */
    public function authorizedStudentIdsForCourse(Course $course): array
    {
        return User::query()
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('profiles.role', UserRole::Student)
            ->where('profiles.account_status', UserAccountStatus::Active)
            ->whereExists(function ($query) use ($course): void {
                $query->selectRaw('1')
                    ->from('enrollments')
                    ->whereColumn('enrollments.student_id', 'users.id')
                    ->where('enrollments.course_id', $course->getKey())
                    ->whereIn('enrollments.status', [EnrollmentStatus::Active, EnrollmentStatus::Completed]);
            })
            ->orderBy('users.id')
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * The same composite, for a specific lesson rather than the whole course.
     *
     * A lesson needs its own published check on top of the course's, and the
     * module's too. Kept beside authorizedStudentIdsForCourse so the two are read
     * together: they are the same rule at two scopes, and a fan-out that picked
     * one of them would notify students who cannot open the destination.
     *
     * @return list<int>
     */
    public function authorizedStudentIdsForLesson(Lesson $lesson): array
    {
        if ($lesson->status !== ContentStatus::Published || $lesson->module->status !== ContentStatus::Published) {
            return [];
        }

        return $this->authorizedStudentIdsForCourse($lesson->module->course);
    }

    /**
     * Marking a Lesson complete needs the same access as reading it.
     */
    public function completeForStudent(User $actor, Lesson $lesson): bool
    {
        return $this->viewForStudent($actor, $lesson);
    }

    public function reorder(User $actor, Module $module): bool
    {
        return $this->ownsCourse($actor, $module->course);
    }

    public function create(User $actor, Module $module): bool
    {
        return $this->ownsCourse($actor, $module->course);
    }

    public function update(User $actor, Lesson $lesson): bool
    {
        return $this->ownsCourse($actor, $lesson->module->course);
    }

    private function isActiveStudent(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Student
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }

    private function ownsCourse(User $actor, Course $course): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active
            && $course->instructor_id === $actor->id;
    }
}
