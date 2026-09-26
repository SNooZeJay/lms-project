<?php

namespace Database\Factories;

use App\Enums\QuizStatus;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'module_id' => null,
            'lesson_id' => null,
            'created_by' => User::factory()->instructor(),
            'title' => 'Quiz '.fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'instructions' => 'Answer every question, then submit once.',
            'position' => 1,
            'status' => QuizStatus::Published,
            'is_required' => false,
            'passing_score_percent' => 80,
            'max_attempts' => 3,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => QuizStatus::Draft]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    /**
     * Attach a simple two-option question with the first option correct.
     *
     * Positions and the answer key are server-owned, so they are written
     * directly rather than mass assigned.
     */
    public function withQuestion(int $position = 1, float $points = 1.0): static
    {
        return $this->afterCreating(function ($quiz) use ($position, $points): void {
            $question = new QuizQuestion;
            $question->forceFill([
                'quiz_id' => $quiz->id,
                'prompt' => 'Question '.$position,
                'position' => $position,
                'points' => $points,
            ]);
            $question->save();

            foreach ([['Correct answer', 1, true], ['Wrong answer', 2, false]] as [$text, $slot, $correct]) {
                $option = new QuizOption;
                $option->forceFill([
                    'question_id' => $question->id,
                    'option_text' => $text,
                    'position' => $slot,
                    'is_correct' => $correct,
                ]);
                $option->save();
            }
        });
    }
}
