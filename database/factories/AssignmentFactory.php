<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'created_by' => User::factory()->instructor(),

            'title' => fake()->sentence(4),

            // Long enough to clear the `min:10` rule, because a factory that
            // produces something the form would refuse is a factory that tests
            // the wrong thing.
            'instructions' => fake()->paragraphs(2, true),

            'form_url' => null,
            'briefing_disk' => null,
            'briefing_path' => null,
            'briefing_mime_type' => null,
            'briefing_byte_size' => null,

            // Marked by default. An assignment with no scale cannot be marked,
            // and a factory default that produces the unusable case means every
            // test that forgot to set it was quietly testing an error page.
            'max_score' => 100,

            'status' => Assignment::PUBLISHED,
            'due_at' => null,
            'closed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => Assignment::DRAFT]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => Assignment::CLOSED,
            'closed_at' => now(),
        ]);
    }

    /**
     * A brief that cannot be marked, on purpose.
     *
     * The case the interface has to explain rather than hide, so it needs a name.
     */
    public function withoutMarkScale(): static
    {
        return $this->state(fn (): array => ['max_score' => null]);
    }

    /**
     * Published, because a student cannot reach a draft and most of what is worth
     * asserting about one is asserted about a student reaching it.
     */
    public function published(): static
    {
        return $this->state(fn (): array => ['status' => Assignment::PUBLISHED]);
    }
}
