<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use App\Support\StudentCourseAccess;

class CoursePolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveInstructor($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveInstructor($actor);
    }

    public function view(User $actor, Course $course): bool
    {
        return $this->isActiveInstructor($actor)
            && $course->instructor_id === $actor->id;
    }

    public function update(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    /**
     * Who is learning on this Course, and how far along they are.
     *
     * Stated separately from `view` so the rule is written down in one place
     * rather than inferred. It is the same rule, because a learner roster is a
     * Course's own information: only the Instructor who owns the Course may see
     * it, and an Administrator does not gain it by being an Administrator.
     * Administration is not teaching, which is the same line `ConversationPolicy`
     * draws for course messages.
     */
    public function viewStudents(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    /**
     * Student course reading is decided by enrollment, not by publication.
     */
    public function viewCourseForStudent(User $actor, Course $course): bool
    {
        return $actor->profile?->role === UserRole::Student
            && $actor->profile?->account_status === UserAccountStatus::Active
            && StudentCourseAccess::allows($actor, $course);
    }

    public function publish(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    public function unpublish(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    public function archive(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    public function restore(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    private function isActiveInstructor(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
