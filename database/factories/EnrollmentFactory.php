<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'course_id' => Course::factory(),
            'status' => EnrollmentStatus::PendingPayment,
            'activated_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'last_accessed_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Completed,
            'activated_at' => now()->subMonth(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
