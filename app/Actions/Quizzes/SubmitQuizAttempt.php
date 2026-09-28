<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizAttemptStatus;
use App\Events\QuizGraded;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quizzes\QuizGrader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Grades and closes a submitted Attempt.
 *
 * The score, percentage, and pass state come from the QuizGrader, which reads
 * the stored Options only. Nothing is read from the request beyond the
 * selected option ids, and submitting twice keeps the first result.
 */
class SubmitQuizAttempt
{
    public function __construct(private readonly QuizGrader $grader) {}

    /**
     * @param  array<int|string, int>  $selected  question id => selected option id
     */
    public function handle(User $actor, Quiz $quiz, QuizAttempt $attempt, array $selected): QuizAttempt
    {
        Gate::forUser($actor)->authorize('viewAttemptForStudent', $attempt);

        if ($attempt->quiz_id !== $quiz->id) {
            abort(404);
        }

        if ($attempt->status !== QuizAttemptStatus::InProgress) {
            throw ValidationException::withMessages([
                'attempt' => 'This attempt was already submitted.',
            ]);
        }

        $clean = collect($selected)
            ->map(fn ($value) => (int) $value)
            ->all();

        $invalid = $this->grader->invalidSelections($quiz, $clean);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'answers' => 'One or more answers do not belong to this quiz.',
            ]);
        }

        return DB::transaction(function () use ($quiz, $attempt, $clean): QuizAttempt {
            $locked = QuizAttempt::query()->lockForUpdate()->find($attempt->id);

            if ($locked === null || $locked->status !== QuizAttemptStatus::InProgress) {
                return $attempt->refresh();
            }

            $result = $this->grader->grade($quiz, $clean);

            $questions = $quiz->questions()->get()->keyBy('id');

            foreach ($clean as $questionId => $optionId) {
                $question = $questions->get((int) $questionId);
                $option = $question?->options->firstWhere('id', (int) $optionId);
                $correct = $option !== null && $option->is_correct;

                // Every field here is server-owned, so nothing is mass assigned.
                $answer = QuizAnswer::query()
                    ->where('attempt_id', $locked->id)
                    ->where('question_id', (int) $questionId)
                    ->first() ?? new QuizAnswer;

                $answer->forceFill([
                    'attempt_id' => $locked->id,
                    'question_id' => (int) $questionId,
                    'selected_option_id' => (int) $optionId,
                    'is_correct' => $correct,
                    'points_awarded' => $correct ? (float) $question->points : 0,
                    'answered_at' => now(),
                ]);
                $answer->save();
            }

            $locked->forceFill([
                'status' => $result['passed'] ? QuizAttemptStatus::Passed : QuizAttemptStatus::Failed,
                'submitted_at' => now(),
                'score_points' => $result['score_points'],
                'total_points' => $result['total_points'],
                'score_percent' => $result['score_percent'],
                'passed' => $result['passed'],
            ]);
            $locked->save();

            /*
             | Only on the path that graded it.
             |
             | The early return above is the double submit: the row was already
             | submitted, and re-announcing it would produce a second set of
             | notices for one set of answers. The lock is the primary guard,
             | exactly as the plan says, and the dedup key on the attempt is the
             | backstop rather than the main defence.
             */
            QuizGraded::dispatch($quiz, $locked);

            return $locked;
        });
    }
}
