<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'course_id' => Course::factory(),
            'title' => $title,
            'description' => fake()->paragraph(),
            'position' => 1,
            'status' => ContentStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Published->value,
        ]);
    }

    public function withPosition(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'position' => $position,
        ]);
    }
}
