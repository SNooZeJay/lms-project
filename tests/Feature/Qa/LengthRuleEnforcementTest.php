<?php

namespace Tests\Feature\Qa;

use App\Enums\CourseLevel;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A narrow probe: is the declared maximum length actually enforced?
 *
 * Written as its own file because a broad fuzz test that reports one of these
 * as a single line among hundreds is easy to dismiss. This asks the question
 * directly, one field and one length at a time, so the answer cannot be lost in
 * the output of a wider run.
 */
class LengthRuleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function declaredLengths(): array
    {
        return [
            // field, allowed maximum, the first length that must be refused
            'course title at 160' => ['title', 160, 161],
            'course description at 5000' => ['description', 5000, 5001],
            'course learning objectives at 5000' => ['learning_objectives', 5000, 5001],
            'course category at 100' => ['category', 100, 101],
        ];
    }

    #[DataProvider('declaredLengths')]
    public function test_the_declared_maximum_is_actually_refused(
        string $field,
        int $allowed,
        int $over
    ): void {
        $instructor = User::factory()->instructor()->create();

        $base = [
            'title' => 'A title',
            'description' => 'A description',
            'learning_objectives' => 'An objective',
            'category' => 'Computing',
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
            'price_minor' => 0,
        ];

        $at = $this->actingAs($instructor)->post('/instructor/courses', array_merge($base, [
            $field => str_repeat('a', $allowed),
        ]));

        $at->assertSessionHasNoErrors();
        $at->assertRedirect();

        $before = Course::query()->count();

        $overResponse = $this->actingAs($instructor)->post('/instructor/courses', array_merge($base, [
            $field => str_repeat('a', $over),
        ]));

        // A browser form POST is refused with a redirect carrying the error in
        // the session, not with 422. Asserting 422 here is what made the first
        // run of this test report a defect that was not there: the redirect
        // landed on "/" because a test request carries no referring page, and the
        // 302 was read as acceptance. The error is in the session, so that is
        // what is asserted.
        $overResponse->assertRedirect();
        $overResponse->assertSessionHasErrors($field);

        $this->assertSame(
            $before,
            Course::query()->count(),
            "The over-length {$field} was refused but a course was still written."
        );
    }

    public function test_a_json_request_to_the_same_route_is_answered_with_422(): void
    {
        // Both shapes have to be refused, and each in its own way. Checking only
        // the browser shape would leave the JSON shape untested, and checking
        // only the JSON shape is what produced the false reading above.
        $instructor = User::factory()->instructor()->create();

        $payload = [
            'title' => str_repeat('a', 161),
            'description' => 'd',
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
            'price_minor' => 0,
        ];

        $this->actingAs($instructor)
            ->postJson('/instructor/courses', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->assertSame(0, Course::query()->count());
    }
}
