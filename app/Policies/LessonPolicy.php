<?php

namespace App\Policies;

use App\Enums\ContentStatus;
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
