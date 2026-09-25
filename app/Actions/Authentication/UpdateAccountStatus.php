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

class UpdateAccountStatus
{
    public function handle(User $actor, User $target, UserAccountStatus $newStatus): ActivityLog
    {
        Gate::forUser($actor)->authorize('updateStatus', $target);
        $target->loadMissing('profile');

        $profile = $target->profile;

        if (! $profile) {
            throw ValidationException::withMessages([
                'status' => 'The target account does not have a profile.',
            ]);
        }

        $previousStatus = $profile->account_status;

        if ($previousStatus === $newStatus) {
            throw ValidationException::withMessages([
                'status' => 'The target already has the selected account status.',
            ]);
        }

        if (
            $profile->role === UserRole::Administrator
            && $previousStatus === UserAccountStatus::Active
            && $newStatus === UserAccountStatus::Suspended
            && $this->activeAdministratorCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'status' => 'The final active Administrator cannot be suspended.',
            ]);
        }

        return DB::transaction(function () use ($actor, $target, $profile, $previousStatus, $newStatus): ActivityLog {
            $profile->forceFill(['account_status' => $newStatus])->save();

            return ActivityLog::create([
                'actor_id' => $actor->id,
                'target_user_id' => $target->id,
                'event_type' => ActivityEventType::AccountStatusChanged,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
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
