<?php

namespace App\Http\Controllers\Notification;

use App\Actions\Notifications\MarkAllNotificationsRead;
use App\Actions\Notifications\MarkNotificationRead;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Read state only.
 *
 * The notification centre page, its list and its badge are a later slice. These
 * two routes are here now because read state is part of the core, and because a
 * stored notice that can never be marked read would make the rest of the core
 * untestable against real behaviour.
 *
 * Both actions answer with a redirect rather than a view. There is no page to
 * return to yet, and inventing a placeholder page now would be a dead screen
 * that later has to be removed.
 */
class NotificationReadController extends Controller
{
    public function update(Request $request, Notification $notification): RedirectResponse
    {
        // The action authorizes. The route model binding has already proved the
        // row exists, and the action proves the actor owns it.
        app(MarkNotificationRead::class)->handle($request->user(), $notification);

        return back();
    }

    public function updateAll(Request $request): RedirectResponse
    {
        app(MarkAllNotificationsRead::class)->handle($request->user());

        return back();
    }
}
