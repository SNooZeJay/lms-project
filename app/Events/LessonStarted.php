<?php

namespace App\Events;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student opened a lesson they had not opened before.
 *
 * This is a transition event and not a view, which is the whole point. Opening
 * the same lesson five times must not produce five notifications, so it is
 * dispatched only where the progress row moves from not started to in progress.
 * That update cannot fire twice for one row, so there is no second state change
 * for a repeat visit to hang a notice on.
 *
 * The Action is responsible for only dispatching this when it saw one affected
 * row. An event that means "somebody looked at a lesson" would push that
 * responsibility onto every listener, and one of them would get it wrong.
 *
 * Implements ShouldDispatchAfterCommit, so the rule cannot be forgotten at a call
 * site. See StudentEnrolled for why it lives on the class.
 */
class LessonStarted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson,
    ) {}
}
