<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveAdministrator($actor);
    }

    public function updateRole(User $actor, User $target): bool
    {
        return $this->isActiveAdministrator($actor)
            && ! $actor->is($target);
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $this->isActiveAdministrator($actor)
            && ! $actor->is($target);
    }

    private function isActiveAdministrator(User $actor): bool
    {
        return $actor->profile?->role === UserRole::Administrator
            && $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
