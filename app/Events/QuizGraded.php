<?php

namespace App\Events;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student submitted a quiz and it was graded.
 *
 * One event, not four. The outcome is on the attempt, and a separate event per
 * outcome would mean several dispatch sites in one Action and several chances to
 * dispatch the wrong pair. The listener reads the graded result and decides what
 * is true, so the pass, fail and retake notices cannot disagree with each other
 * about the same submission.
 *
 * The pass or fail is the graded answers against the quiz's own configured rules.
 * Nothing here decides it, and nothing here holds a passing mark, which is what
 * the specification means by not hardcoding a requirement.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class QuizGraded implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Quiz $quiz,
        public readonly QuizAttempt $attempt,
    ) {}
}
