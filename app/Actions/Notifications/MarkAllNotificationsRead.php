<?php

namespace App\Actions\Notifications;

use App\Models\Notification;
use App\Models\User;

/**
 * Clear a person's whole unread count in one step.
 *
 * The update is scoped to the actor's own rows, so it cannot touch anybody
 * else's notices even if the caller is wrong about who it is acting for. There
 * is no policy check here because there is no resource to check: the resource is
 * "every notice belonging to this account", and that is what the query says.
 */
class MarkAllNotificationsRead
{
    public function handle(User $actor): int
    {
        return Notification::query()
            ->where('user_id', $actor->id)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
