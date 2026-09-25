<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class RoleBasedDestination
{
    public static function for(User $user): string
    {
        return match ($user->profile?->role) {
            UserRole::Student => route('student.dashboard'),
            UserRole::Instructor => route('instructor.dashboard'),
            UserRole::Administrator => route('administrator.dashboard'),
            default => route('account.profile'),
        };
    }
}
