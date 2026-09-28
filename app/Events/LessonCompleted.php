<?php

namespace App\Events;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student finished a lesson.
 *
 * Dispatched only when the progress row actually changed to completed. Marking
 * an already complete lesson complete is a no-op and produces no event, for the
 * same reason LessonStarted only fires on a real transition.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class LessonCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson,
    ) {}
}
