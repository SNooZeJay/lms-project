<?php

namespace App\Actions\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Mark one notice read by the person it was sent to.
 *
 * Read state belongs to the recipient and to nobody else, so the owner check
 * lives here as well as in the policy. The policy decides what the route may
 * offer; this decides what the action will actually do. A route that forgets to
 * ask the policy still cannot mark somebody else's notice, because the action
 * asks on its own.
 */
class MarkNotificationRead
{
    /**
     * @return bool true when this call is what changed it, false when the
     *              notice was already read. A second click is not an error.
     */
    public function handle(User $actor, Notification $notification): bool
    {
        Gate::forUser($actor)->authorize('markRead', $notification);

        if ($notification->isRead()) {
            return false;
        }

        $notification->forceFill(['read_at' => now()])->save();

        return true;
    }
}
