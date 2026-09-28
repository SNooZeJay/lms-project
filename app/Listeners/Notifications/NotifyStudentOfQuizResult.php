<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Events\QuizGraded;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Gate;

/**
 * Tells a student how their quiz went, and whether they may try again.
 *
 * This is the row in the plan that carries the specification's hardest
 * requirement about not hardcoding a passing mark, and the whole listener is
 * built to avoid doing that.
 *
 * The pass and the fail both come from the graded attempt, which was scored
 * against the quiz's own configured rules. The retake is offered only when the
 * quiz's own `max_attempts` says one is left, so the number of tries is stored
 * on the quiz with a range and is enforced by StartQuizAttempt. Nothing here
 * holds a threshold, and a quiz configured for two attempts offers exactly one
 * retake without this file changing.
 *
 * A pass writes one row. A fail writes one, plus a second only when a retake is
 * actually available, because "you failed" without "here is what you can do" is
 * a dead end.
 */
class NotifyStudentOfQuizResult
{
    public function __construct(private readonly RecordNotification $notify) {}

    public function handle(QuizGraded $event): void
    {
        $attempt = $event->attempt;
        $quiz = $event->quiz;
        $student = $attempt->student;
        $course = $quiz->course;

        $score = number_format((float) $attempt->score_percent, 0);

        if ($attempt->passed) {
            $this->notify->handle(
                $student,
                NotificationType::QuizPassed,
                'You passed '.$quiz->title,
                "You scored {$score}% and passed \"{$quiz->title}\".",
                course: $course,
                dedupKey: "attempt:{$attempt->id}:passed",
                link: $this->attemptLink($quiz, $attempt),
                authorizeLink: fn ($who): bool => Gate::forUser($who)->allows('viewAttemptForStudent', $attempt),
                subjectType: 'quiz_attempt',
                subjectId: $attempt->id,
            );

            return;
        }

        $remaining = $this->attemptsLeft($quiz, $student->id);

        $this->notify->handle(
            $student,
            NotificationType::QuizFailed,
            'You did not pass '.$quiz->title,
            $remaining > 0
                ? "You scored {$score}%. You have {$remaining} ".($remaining === 1 ? 'attempt' : 'attempts').' left.'
                : "You scored {$score}%. You have used all {$quiz->max_attempts} attempts.",
            course: $course,
            dedupKey: "attempt:{$attempt->id}:failed",
            link: $this->attemptLink($quiz, $attempt),
            authorizeLink: fn ($who): bool => Gate::forUser($who)->allows('viewAttemptForStudent', $attempt),
            subjectType: 'quiz_attempt',
            subjectId: $attempt->id,
        );

        if ($remaining <= 0) {
            return;
        }

        $this->notify->handle(
            $student,
            NotificationType::RetakeRequired,
            'You can retry '.$quiz->title,
            "You have {$remaining} ".($remaining === 1 ? 'attempt' : 'attempts').' left for this quiz.',
            course: $course,
            dedupKey: "attempt:{$attempt->id}:retake",
            link: $this->attemptLink($quiz, $attempt),
            authorizeLink: fn ($who): bool => Gate::forUser($who)->allows('viewAttemptForStudent', $attempt),
            subjectType: 'quiz_attempt',
            subjectId: $attempt->id,
        );
    }

    /**
     * How many attempts this student has left, read from the quiz's own limit.
     *
     * Counted rather than derived from the attempt number, because an attempt is
     * refused before a row exists when the limit is reached and the two can
     * disagree. A number that comes from the same source StartQuizAttempt checks
     * cannot tell a student they have a retake and then refuse them.
     */
    private function attemptsLeft(Quiz $quiz, int $studentId): int
    {
        $used = $quiz->attempts()->where('student_id', $studentId)->count();

        return max(0, (int) $quiz->max_attempts - $used);
    }

    /**
     * The result page, which is the only place the score is shown.
     *
     * Three parameters because the route nests the quiz under the course. Passing
     * the course rather than letting the route infer it keeps the link correct
     * if the route is ever given a different shape.
     */
    private function attemptLink(Quiz $quiz, QuizAttempt $attempt): string
    {
        return route('student.quizzes.attempts.result', [$quiz->course, $quiz, $attempt]);
    }
}
