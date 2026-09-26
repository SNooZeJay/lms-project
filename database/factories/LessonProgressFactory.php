<?php

namespace Database\Factories;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'status' => LessonProgressStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
            'last_viewed_at' => null,
        ];
    }

    /**
     * Keep student_id consistent with the enrollment unless a test sets it.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (LessonProgress $progress): void {
            if ($progress->student_id === null) {
                $progress->student_id = $progress->enrollment?->student_id;
            }
        });
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LessonProgressStatus::InProgress,
            'started_at' => now(),
            'last_viewed_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LessonProgressStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'last_viewed_at' => now(),
        ]);
    }
}
