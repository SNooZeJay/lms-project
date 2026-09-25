<?php

namespace App\Actions\Authentication;

use App\Enums\ActivityEventType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignUserRole
{
    public function handle(User $actor, User $target, UserRole $newRole): ActivityLog
    {
        Gate::forUser($actor)->authorize('updateRole', $target);
        $target->loadMissing('profile');

        if (! $target->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'role' => 'The target account must have a verified email before its role can change.',
            ]);
        }

        $profile = $target->profile;

        if (! $profile) {
            throw ValidationException::withMessages([
                'role' => 'The target account does not have a profile.',
            ]);
        }

        $previousRole = $profile->role;

        if ($previousRole === $newRole) {
            throw ValidationException::withMessages([
                'role' => 'The target already has the selected role.',
            ]);
        }

        if (
            $previousRole === UserRole::Administrator
            && $profile->account_status === UserAccountStatus::Active
            && $newRole !== UserRole::Administrator
            && $this->activeAdministratorCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' => 'The final active Administrator cannot be demoted.',
            ]);
        }

        return DB::transaction(function () use ($actor, $target, $profile, $previousRole, $newRole): ActivityLog {
            $profile->forceFill(['role' => $newRole])->save();

            return ActivityLog::create([
                'actor_id' => $actor->id,
                'target_user_id' => $target->id,
                'event_type' => ActivityEventType::RoleChanged,
                'previous_role' => $previousRole,
                'new_role' => $newRole,
            ]);
        });
    }

    private function activeAdministratorCount(): int
    {
        return Profile::query()
            ->where('role', UserRole::Administrator->value)
            ->where('account_status', UserAccountStatus::Active->value)
            ->count();
    }
}
