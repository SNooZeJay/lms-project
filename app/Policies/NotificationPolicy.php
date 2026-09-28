<?php

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Models\Notification;
use App\Models\User;

/**
 * A notice is readable and markable only by the account it was sent to.
 *
 * There is no administrator override. An administrator who can read every
 * student's notices can also see that a student was suspended, was enrolled in
 * a course they were later removed from, or failed a quiz. Read state is a
 * private record of what somebody was told, so it stays private.
 *
 * A suspended account cannot mark anything read either, because the shell gives
 * a suspended account no navigation at all. See App\Support\Navigation.
 */
class NotificationPolicy
{
    public function view(User $actor, Notification $notification): bool
    {
        return $this->isActiveRecipient($actor, $notification);
    }

    public function markRead(User $actor, Notification $notification): bool
    {
        return $this->isActiveRecipient($actor, $notification);
    }

    private function isActiveRecipient(User $actor, Notification $notification): bool
    {
        if ($actor->profile?->account_status !== UserAccountStatus::Active) {
            return false;
        }

        return $notification->user_id === $actor->id;
    }
}
