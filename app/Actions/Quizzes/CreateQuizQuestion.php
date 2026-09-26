<?php

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
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
                'position' => ((int) $quiz->questions()->max('position')) + 1,
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
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
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

        $correct = collect($options)->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->count();

        if ($correct !== 1) {
            throw ValidationException::withMessages([
                'options' => 'Mark exactly one option as the correct answer.',
            ]);
        }
    }
}
