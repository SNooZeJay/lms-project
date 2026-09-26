<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuizStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * One shared rule for "may this Student read this Quiz".
 */
class StudentQuizAccess
{
    public static function allows(User $student, Quiz $quiz): bool
    {
        if (! self::isActiveStudent($student)) {
            return false;
        }

        if ($quiz->status !== QuizStatus::Published || $quiz->course->status !== CourseStatus::Published) {
            return false;
        }

        $module = $quiz->module;

        if ($module !== null && $module->status !== ContentStatus::Published) {
            return false;
        }

        return self::hasGrantingEnrollment($student, $quiz);
    }

    /**
     * A Student may only ever reach their own Attempt.
     */
    public static function ownsAttempt(User $student, QuizAttempt $attempt): bool
    {
        return $attempt->student_id === $student->id && self::allows($student, $attempt->quiz);
    }

    public static function isActiveStudent(User $student): bool
    {
        return $student->profile?->role === UserRole::Student
            && $student->profile?->account_status === UserAccountStatus::Active;
    }

    private static function hasGrantingEnrollment(User $student, Quiz $quiz): bool
    {
        return $quiz->course->enrollments()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->exists();
    }
}
