<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The notification centre.
 *
 * The topbar bell is a shortcut into this page rather than the whole of it. The
 * panel shows the most recent notices, and this is where the full history lives
 * with its pagination, because a dropdown that grows without limit is a
 * dropdown that eventually runs off the screen.
 */
class NotificationCentreController extends Controller
{
    public function __invoke(Request $request): View
    {
        $notifications = Notification::pageFor($request->user());

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => Notification::unreadCountFor($request->user()),
        ]);
    }
}
