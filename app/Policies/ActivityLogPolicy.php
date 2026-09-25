<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Administrator
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
