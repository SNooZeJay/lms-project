<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use App\Support\StudentQuizAccess;

class QuizPolicy
{
    public function create(User $actor, Course $course): bool
    {
        return $this->ownsCourse($actor, $course);
    }

    public function update(User $actor, Quiz $quiz): bool
    {
        return $this->ownsCourse($actor, $quiz->course);
    }

    public function archive(User $actor, Quiz $quiz): bool
    {
        return $this->ownsCourse($actor, $quiz->course);
    }

    public function viewForStudent(User $actor, Quiz $quiz): bool
    {
        return StudentQuizAccess::allows($actor, $quiz);
    }

    public function startForStudent(User $actor, Quiz $quiz): bool
    {
        return StudentQuizAccess::allows($actor, $quiz);
    }

    private function ownsCourse(User $actor, Course $course): bool
    {
        return $actor->profile?->role === UserRole::Instructor
            && $actor->profile?->account_status === UserAccountStatus::Active
            && $course->instructor_id === $actor->id;
    }
}
