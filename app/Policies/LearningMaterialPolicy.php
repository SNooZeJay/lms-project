<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\User;

class LearningMaterialPolicy
{
    public function create(User $actor, Lesson $lesson): bool
    {
        return $this->ownsCourse($actor, $lesson->module->course);
    }

    public function update(User $actor, LearningMaterial $material): bool
    {
        return $this->ownsCourse($actor, $material->lesson->module->course);
    }

    private function ownsCourse(User $actor, Course $course): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active
            && $course->instructor_id === $actor->id;
    }
}
