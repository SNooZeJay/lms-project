<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Events\QuizGraded;
use App\Events\QuizStarted;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tells an instructor that a student started or submitted a quiz.
 *
 * One listener for both, for the same reason as the lesson listener: they differ
 * in a word, and duplicating the recipient rules is duplicating the chance to get
 * them wrong.
 */
class NotifyInstructorOfQuizActivity
{
    public function __construct(private readonly RecordNotification $notify) {}

    public function handleStarted(QuizStarted $event): void
    {
        $this->record(
            $event->quiz,
            $event->attempt,
            NotificationType::QuizStarted,
            'started',
            null,
        );
    }

    public function handleGraded(QuizGraded $event): void
    {
        $this->record(
            $event->quiz,
            $event->attempt,
            NotificationType::QuizCompleted,
            'submitted',
            $event->attempt->score_percent,
        );
    }

    private function record(
        Quiz $quiz,
        QuizAttempt $attempt,
        NotificationType $type,
        string $verb,
        ?float $scorePercent,
    ): void {
        $course = $quiz->course;
        $instructor = $course->instructor;

        if ($instructor === null) {
            return;
        }

        $student = $attempt->student;

        $body = "{$student->name} {$verb} \"{$quiz->title}\" in {$course->title}.";

        if ($scorePercent !== null) {
            $body .= ' Scored '.number_format((float) $scorePercent, 0).'%.';
        }

        $this->notify->handle(
            $instructor,
            $type,
            "{$student->name} {$verb} a quiz",
            $body,
            course: $course,
            /*
             | Keyed on the attempt and the type.
             |
             | The plan gives `attempt:{id}` for both QUIZ_STARTED and
             | QUIZ_COMPLETED, which cannot be right for the same reason as the
             | lesson keys: the key is unique per recipient, so the notice written
             | when the attempt opened would suppress the one written when it was
             | submitted, and an instructor would never learn that a quiz was
             * handed in. The test that asserts both notices is what found it.
             */
            dedupKey: "attempt:{$attempt->id}:{$type->value}",
            link: route('instructor.courses.show', $course),
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('view', $course),
            subjectType: 'quiz_attempt',
            subjectId: $attempt->id,
        );
    }
}
