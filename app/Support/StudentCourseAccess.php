<?php

namespace App\Support;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\User;

/**
 * One shared rule for "may this Student read this Course".
 */
class StudentCourseAccess
{
    public static function allows(User $student, Course $course): bool
    {
        return Course::query()
            ->whereKey($course->getKey())
            ->whereHas('enrollments', function ($query) use ($student): void {
                $query->where('student_id', $student->getKey())
                    ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed]);
            })
            ->exists();
    }
}
