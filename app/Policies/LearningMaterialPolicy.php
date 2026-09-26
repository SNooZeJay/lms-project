<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\User;
use App\Support\StudentCourseAccess;

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

    /**
     * An Administrator may review any material. Everyone else needs the exact
     * relationship that lets them already see the Lesson.
     */
    public function download(User $actor, LearningMaterial $material): bool
    {
        if ($actor->profile?->account_status !== UserAccountStatus::Active) {
            return false;
        }

        $course = $material->lesson->module->course;

        return match ($actor->profile?->role) {
            UserRole::Administrator => true,
            UserRole::Instructor => $this->ownsCourse($actor, $course),
            UserRole::Student => StudentCourseAccess::allows($actor, $course)
                && $material->lesson->status === ContentStatus::Published
                && $material->lesson->module->status === ContentStatus::Published,
            default => false,
        };
    }

    private function ownsCourse(User $actor, Course $course): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active
            && $course->instructor_id === $actor->id;
    }
}
