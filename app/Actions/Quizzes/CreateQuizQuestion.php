<?php

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a Question with its Options.
 *
 * The key is server-owned and validated here: exactly one Option must be
 * correct, and there must be at least two Options.
 */
class CreateQuizQuestion
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Quiz $quiz, array $data): QuizQuestion
    {
        Gate::forUser($actor)->authorize('update', $quiz);

        $options = $data['options'] ?? [];

        $this->ensureValidKey($options);

        return DB::transaction(function () use ($quiz, $data, $options): QuizQuestion {
            $question = new QuizQuestion;
            $question->forceFill([
                'quiz_id' => $quiz->id,
                'prompt' => $data['prompt'],
                'position' => Position::reserve($quiz, $quiz->questions()),
                'points' => $data['points'] ?? 1,
                'explanation' => $data['explanation'] ?? null,
            ]);
            $question->save();

            foreach (array_values($options) as $index => $option) {
                $row = new QuizOption;
                $row->forceFill([
                    'question_id' => $question->id,
                    'option_text' => $option['option_text'],
                    'position' => $index + 1,
                    // isMarkedCorrect, not a cast. A cast would turn the string
                    // 'yes' into true, which is how two options could both be
                    // stored as correct.
                    'is_correct' => $this->isMarkedCorrect($option['is_correct'] ?? null),
                    'explanation' => $option['explanation'] ?? null,
                ]);
                $row->save();
            }

            return $question;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function ensureValidKey(array $options): void
    {
        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'A question needs at least two options.',
            ]);
        }

        // Only an explicit true marks the answer. A loose cast is not enough,
        // because PHP treats the string 'yes', the string '1', and the integer 1
        // as true, so a question could be written with two correct options and
        // every response would then score as right. The value comes from a
        // checkbox, so a form sends '1', 'on', or nothing at all; anything else
        // is not a mark the person made and is refused rather than guessed at.
        $marked = collect($options)->filter(
            fn ($option) => $this->isMarkedCorrect($option['is_correct'] ?? null)
        );

        if ($marked->count() !== 1) {
            throw ValidationException::withMessages([
                'options' => 'Mark exactly one option as the correct answer.',
            ]);
        }

        // Refused rather than ignored, so a tampered payload is answered with a
        // reason instead of quietly producing a question whose key disagrees with
        // what the person marked.
        foreach ($options as $index => $option) {
            if (! $this->isAcceptedMarker($option['is_correct'] ?? null)) {
                throw ValidationException::withMessages([
                    "options.{$index}.is_correct" => 'The correct answer marker must be true or left empty.',
                ]);
            }
        }
    }

    /**
     * Whether a value is a marker a real form could have produced.
     *
     * An unchecked checkbox posts nothing, so an absent key is the ordinary case
     * rather than an error. The accepted strings are the ones a browser sends for
     * a checked box and a set '1' field.
     */
    private function isAcceptedMarker(mixed $value): bool
    {
        if ($value === null || $value === true || $value === false) {
            return true;
        }

        if (! is_scalar($value)) {
            return false;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['', '0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'],
            true
        );
    }

    /**
     * Whether a value counts as an explicit mark.
     *
     * A checkbox posts a string, so '1', 'on', and 'true' are the values a real
     * form produces. Everything else is treated as unmarked.
     */
    private function isMarkedCorrect(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }

        return is_scalar($value)
            && in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }
}
