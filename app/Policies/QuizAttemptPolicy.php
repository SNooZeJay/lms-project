<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\StudentQuizAccess;

class QuizAttemptPolicy
{
    /**
     * A Student reaches only their own Attempt, and only while the Quiz is
     * still readable. An Administrator may review any Attempt.
     */
    public function viewAttemptForStudent(User $actor, QuizAttempt $attempt): bool
    {
        return StudentQuizAccess::ownsAttempt($actor, $attempt);
    }
}
