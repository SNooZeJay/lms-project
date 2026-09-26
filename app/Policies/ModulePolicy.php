<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function create(User $actor, Course $course): bool
    {
        return $this->ownsCourse($actor, $course);
    }

    public function update(User $actor, Module $module): bool
    {
        return $this->ownsCourse($actor, $module->course);
    }

    public function reorder(User $actor, Course $course): bool
    {
        return $this->ownsCourse($actor, $course);
    }

    private function ownsCourse(User $actor, Course $course): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active
            && $course->instructor_id === $actor->id;
    }
}
