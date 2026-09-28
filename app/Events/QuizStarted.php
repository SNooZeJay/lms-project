<?php

namespace App\Events;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student began a quiz attempt.
 *
 * Dispatched when the attempt row is created, which happens once per attempt and
 * cannot repeat, so nothing beyond the attempt id is needed to tell one from
 * another.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class QuizStarted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quiz $quiz,
        public readonly QuizAttempt $attempt,
    ) {}
}
