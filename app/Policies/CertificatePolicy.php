<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;
use App\Support\StudentCourseAccess;

class CertificatePolicy
{
    /**
     * A Student reads only their own certificate, and only while they can still
     * read the Course it came from.
     */
    public function view(User $actor, Certificate $certificate): bool
    {
        if ($actor->profile?->role !== UserRole::Student
            || $actor->profile?->account_status !== UserAccountStatus::Active) {
            return false;
        }

        return $certificate->student_id === $actor->id
            && StudentCourseAccess::allows($actor, $certificate->course);
    }

    public function revoke(User $actor, Certificate $certificate): bool
    {
        return $this->isAdministrator($actor);
    }

    public function reissue(User $actor, Certificate $certificate): bool
    {
        return $this->isAdministrator($actor);
    }

    private function isAdministrator(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Administrator
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
