<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student now holds an enrollment in a course.
 *
 * The after-commit rule lives on the class rather than at the call site, and
 * that is deliberate. Written as `->afterCommit()` on the dispatch, it is one
 * thing every call site has to remember, and the first one that forgets produces
 * a notification about a transaction that then rolled back: a lie the instructor
 * reads. Implementing the interface makes the rule a property of the event, so
 * there is no way to dispatch one of these early.
 */
class StudentEnrolled implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Enrollment $enrollment) {}
}
