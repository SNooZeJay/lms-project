<?php

namespace App\Events;

use App\Enums\ContentStatus;
use App\Models\Course;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Something new was added to a course, or the course itself became visible.
 *
 * Created only from the Create actions, never from Update. That is what answers
 * the specification's "do not notify when the instructor simply edits existing
 * content" structurally rather than by a rule: CreateLesson and UpdateLesson are
 * different Actions and only the first one can dispatch, so there is no content
 * comparison to get wrong and nothing to misconfigure. ArchiveContent
 * deliberately dispatches nothing, because only new content is announced.
 *
 * Carries the subject as a model rather than a type name, because the listener
 * has to render something sensible for a lesson, a module and a learning
 * material, and a string would push that choice into every listener.
 *
 * The status travels with it so a listener never has to re-read the model and
 * find it has changed since. A published event about something now archived is
 * a notice about something nobody can open.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class ContentPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Course $course,
        public readonly Model $subject,
        public readonly ContentStatus $status,
    ) {}

    /**
     * Whether this is the course itself rather than something inside it.
     *
     * The two are announced in the same words: new material is available. The
     * difference is only the link, and a separate event class for each would
     * duplicate the whole listener.
     */
    public function isCourseItself(): bool
    {
        return $this->subject->is($this->course);
    }
}
