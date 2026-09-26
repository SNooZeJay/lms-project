<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

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

    public function publish(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    public function unpublish(User $actor, Course $course): bool
    {
        return $this->view($actor, $course);
    }

    private function isActiveInstructor(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
