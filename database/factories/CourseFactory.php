<?php

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'instructor_id' => User::factory()->instructor(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->paragraph(),
            'learning_objectives' => implode(' ', fake()->sentences(3)),
            'category' => fake()->randomElement(['Computing', 'Programming', 'Networks']),
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
            'price_minor' => 0,
            'currency' => 'PHP',
            'status' => CourseStatus::Draft->value,
            'published_at' => null,
        ];
    }
}
