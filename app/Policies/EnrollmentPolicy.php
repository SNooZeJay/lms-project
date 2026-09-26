<?php

namespace App\Policies;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveStudent($actor);
    }

    public function view(User $actor, Enrollment $enrollment): bool
    {
        return $this->isActiveStudent($actor)
            && $enrollment->student_id === $actor->id;
    }

    public function create(User $actor, Course $course): bool
    {
        return $this->isActiveStudent($actor)
            && $course->status === CourseStatus::Published;
    }

    /**
     * Only a Student with a pending enrollment for a paid published Course may
     * start a checkout.
     */
    public function pay(User $actor, Enrollment $enrollment): bool
    {
        if (! $this->isActiveStudent($actor) || $enrollment->student_id !== $actor->id) {
            return false;
        }

        if ($enrollment->status !== EnrollmentStatus::PendingPayment) {
            return false;
        }

        $course = $enrollment->course;

        return $course->status === CourseStatus::Published
            && $course->course_type === CourseType::Paid
            && (int) $course->price_minor > 0;
    }

    /**
     * Only the Student who owns the Enrollment may complete it.
     */
    public function complete(User $actor, Enrollment $enrollment): bool
    {
        return $this->grantsAccess($enrollment, $actor);
    }

    public function isActiveStudent(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Student
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }

    public function grantsAccess(Enrollment $enrollment, User $actor): bool
    {
        return $this->view($actor, $enrollment)
            && in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true);
    }
}
