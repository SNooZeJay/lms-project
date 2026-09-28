<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An announcement was published to one course or to the platform.
 *
 * One event for both, because the fan-out differs in the recipient set and in
 * nothing else: the words, the link, the dedup key shape and the rule that a
 * suspended account hears nothing are identical. Two events would be two
 * listeners where one differs by four lines, and the four lines would drift.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why the rule
 * lives on the class.
 */
class AnnouncementPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Announcement $announcement) {}
}
