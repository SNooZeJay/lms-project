<?php

namespace Database\Factories;

use App\Enums\QuizAttemptStatus;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'student_id' => null,
            'enrollment_id' => Enrollment::factory(),
            'attempt_number' => 1,
            'status' => QuizAttemptStatus::InProgress,
            'started_at' => now(),
            'submitted_at' => null,
            'score_points' => null,
            'total_points' => null,
            'score_percent' => null,
            'passed' => null,
        ];
    }

    /**
     * Keep student_id consistent with the enrollment unless a test sets it.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (QuizAttempt $attempt): void {
            if ($attempt->student_id === null) {
                $attempt->student_id = $attempt->enrollment?->student_id;
            }
        });
    }

    public function passed(float $percent = 100.0): static
    {
        return $this->state(fn () => [
            'status' => QuizAttemptStatus::Passed,
            'submitted_at' => now(),
            'score_points' => 1,
            'total_points' => 1,
            'score_percent' => $percent,
            'passed' => true,
        ]);
    }

    public function failed(float $percent = 0.0): static
    {
        return $this->state(fn () => [
            'status' => QuizAttemptStatus::Failed,
            'submitted_at' => now(),
            'score_points' => 0,
            'total_points' => 1,
            'score_percent' => $percent,
            'passed' => false,
        ]);
    }
}
