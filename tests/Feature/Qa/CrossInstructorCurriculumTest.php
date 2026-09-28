<?php

namespace Tests\Feature\Qa;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A second Instructor cannot author, archive, restore or reorder somebody
 * else's curriculum.
 *
 * Why this file exists and is narrow: an earlier version of it also re-tested
 * role changes, certificate revocation, announcement publishing and thread
 * privacy. Every one of those already had a test, and a better one, because those
 * paths assert both the refusal and that the row did not change. Repeating them
 * here would have added a second, weaker copy of a property that is already
 * pinned, which is worse than adding nothing.
 *
 * What was genuinely uncovered is the writing half of the curriculum. Course
 * editing is covered by ContentEditingTest and publication by
 * CoursePublishingTest, but every store, archive, restore and reorder route had
 * no second-Instructor case at all. Those are the routes below.
 *
 * Each payload is deliberately valid. A request that fails validation never
 * reaches the Policy, so a 422 would look like a refusal while proving nothing
 * about authorization, and the assertion below rejects 422 for that reason.
 */
class CrossInstructorCurriculumTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $stranger;

    private Course $course;

    private Module $module;

    private Lesson $lesson;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->instructor()->create();
        $this->stranger = User::factory()->instructor()->create();

        $this->course = Course::factory()->for($this->owner, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);

        $this->module = Module::factory()->for($this->course, 'course')->create(['position' => 1]);
        $this->lesson = Lesson::factory()->for($this->module, 'module')->create(['position' => 1]);
        $this->quiz = Quiz::factory()->for($this->course, 'course')->create(['position' => 1]);
    }

    /**
     * Every writing route on a course this Instructor does not own.
     *
     * The two reorder routes are not here. Their payload must name real record
     * identifiers, which a static provider cannot know, and a payload naming the
     * wrong ones is refused by validation rather than by the Policy. That
     * produces a redirect, which is indistinguishable from a successful write,
     * so those two are asserted separately using identifiers from the fixture.
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function writingRoutes(): array
    {
        return [
            'restore the course' => ['instructor.courses.restore', 'POST', []],
            'add a module' => ['instructor.courses.modules.store', 'POST', ['title' => 'Injected module']],
            'archive a module' => ['instructor.courses.modules.archive', 'POST', []],
            'restore a module' => ['instructor.courses.modules.restore', 'POST', []],
            'add a lesson' => ['instructor.courses.modules.lessons.store', 'POST', ['title' => 'Injected lesson']],
            'archive a lesson' => ['instructor.courses.modules.lessons.archive', 'POST', []],
            'restore a lesson' => ['instructor.courses.modules.lessons.restore', 'POST', []],
            'add a material' => [
                'instructor.courses.materials.store',
                'POST',
                ['title' => 'Injected material', 'material_type' => 'text', 'content_text' => 'Injected body'],
            ],
            'add a quiz' => ['instructor.courses.quizzes.store', 'POST', ['title' => 'Injected quiz']],
        ];
    }

    #[DataProvider('writingRoutes')]
    public function test_a_second_instructor_is_refused(string $route, string $method, array $payload): void
    {
        $response = $this->send($method, route($route, $this->parametersFor($route)), $payload);

        $status = $response->getStatusCode();

        /*
         | 403 or 404, and nothing else.
         |
         | A redirect was originally accepted here, on the grounds that it is not a
         | success. That is wrong, and it was found by removing both authorization
         | layers and watching the test pass: a successful write answers with a
         | redirect too. Every one of these routes answers 403 to a stranger, so
         | accepting 302 bought nothing and cost the whole point of the assertion.
         */
        $this->assertContains(
            $status,
            [403, 404],
            "{$route} answered {$status} to an Instructor acting on a course they do not own. "
            .'A 302 means the write was allowed and redirected, and a 422 means the payload '
            .'never reached the Policy. Neither is a refusal.'
        );
    }

    /**
     * Reordering, with a payload the validator will actually accept.
     *
     * Two things go wrong if this is built from guessed values. The validator
     * requires a position for every active Module and requires them to run from
     * one with no gaps, so a partial or out-of-range payload is turned away by
     * validation. Validation answers with a redirect, which is the same answer a
     * permitted write gives, so a reorder test built carelessly passes whether or
     * not the Policy is doing anything at all.
     *
     * So the course here has two Modules and the payload genuinely swaps them,
     * which means a refusal can only have come from the Policy and a pass would
     * mean the order really moved.
     */
    public function test_a_second_instructor_cannot_reorder_the_curriculum(): void
    {
        $secondModule = Module::factory()->for($this->course, 'course')->create(['position' => 2]);

        $swapped = [$this->module->id => 2, $secondModule->id => 1];

        $this->actingAs($this->stranger)
            ->patch(route('instructor.courses.modules.reorder', $this->course), ['positions' => $swapped])
            ->assertForbidden();

        $this->assertSame(1, (int) $this->module->fresh()->position, 'A module was moved by somebody with no right to move it.');
        $this->assertSame(2, (int) $secondModule->fresh()->position, 'A module was moved by somebody with no right to move it.');

        // And the same for lessons, which are reordered inside a Module.
        $secondLesson = Lesson::factory()->for($this->module, 'module')->create(['position' => 2]);

        $this->actingAs($this->stranger)
            ->patch(route('instructor.courses.modules.lessons.reorder', [$this->course, $this->module]), [
                'positions' => [$this->lesson->id => 2, $secondLesson->id => 1],
            ])
            ->assertForbidden();

        $this->assertSame(1, (int) $this->lesson->fresh()->position);
        $this->assertSame(2, (int) $secondLesson->fresh()->position);
    }

    public function test_nothing_was_actually_changed(): void
    {
        // The same requests again, in one place, so the claim that nothing landed
        // is checked against the rows rather than against a status code.
        $this->actingAs($this->stranger)->post(
            route('instructor.courses.modules.store', $this->course),
            ['title' => 'Injected module']
        );

        $this->actingAs($this->stranger)->post(
            route('instructor.courses.modules.lessons.store', [$this->course, $this->module]),
            ['title' => 'Injected lesson']
        );

        $this->actingAs($this->stranger)->post(
            route('instructor.courses.quizzes.store', $this->course),
            ['title' => 'Injected quiz']
        );

        $this->actingAs($this->stranger)->post(
            route('instructor.courses.modules.archive', [$this->course, $this->module])
        );

        $this->actingAs($this->stranger)->patch(
            route('instructor.courses.modules.reorder', $this->course),
            ['positions' => [$this->module->id => 7]]
        );

        $this->assertDatabaseMissing('modules', ['title' => 'Injected module']);
        $this->assertDatabaseMissing('lessons', ['title' => 'Injected lesson']);
        $this->assertDatabaseMissing('quizzes', ['title' => 'Injected quiz']);

        $this->assertSame(
            ContentStatus::Draft,
            $this->module->fresh()->status,
            'A second Instructor archived a module they do not own.'
        );

        $this->assertSame(
            1,
            (int) $this->module->fresh()->position,
            'A second Instructor reordered a curriculum they do not own.'
        );
    }

    /**
     * A module from another course cannot be reached through this course.
     *
     * The address is well formed and every identifier is real. The only thing
     * wrong with it is that the module belongs somewhere else, and a mismatched
     * parent is the shape an attacker assembles by hand.
     */
    public function test_a_module_from_another_course_is_refused_through_this_one(): void
    {
        $otherCourse = Course::factory()->for($this->owner, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);

        $foreignModule = Module::factory()->for($otherCourse, 'course')->create(['position' => 1]);

        $this->actingAs($this->owner)
            ->post(route('instructor.courses.modules.lessons.store', [$this->course, $foreignModule]), [
                'title' => 'Injected lesson',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('lessons', ['title' => 'Injected lesson']);
    }

    /**
     * A material from another lesson cannot be edited through this one.
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function materialRoutes(): array
    {
        return [
            'edit a material' => [
                'instructor.courses.materials.update',
                'PATCH',
                ['title' => 'Hijacked', 'material_type' => 'text', 'content_text' => 'Hijacked body'],
            ],
        ];
    }

    #[DataProvider('materialRoutes')]
    public function test_a_second_instructor_cannot_edit_a_material(string $route, string $method, array $payload): void
    {
        $material = LearningMaterial::factory()->for($this->lesson, 'lesson')->create();

        $response = $this->send($method, route($route, [$this->course, $this->module, $this->lesson, $material]), $payload);

        $this->assertContains($response->getStatusCode(), [403, 404], "{$route} was not refused.");

        $this->assertNotSame(
            'Hijacked',
            $material->fresh()->title,
            'A second Instructor changed a material they do not own.'
        );
    }

    /**
     * Sends the request as the second Instructor, by verb.
     *
     * The test helper exposes one method per verb and no generic entry point, so
     * the verb has to be dispatched rather than passed through.
     */
    private function send(string $method, string $uri, array $payload = [])
    {
        $acting = $this->actingAs($this->stranger);

        return match (strtoupper($method)) {
            'POST' => $acting->post($uri, $payload),
            'PATCH', 'PUT' => $acting->patch($uri, $payload),
            'DELETE' => $acting->delete($uri, $payload),
            default => $acting->get($uri),
        };
    }

    /**
     * A quiz from another course cannot be reached through this one.
     */
    public function test_a_quiz_from_another_course_is_refused_through_this_one(): void
    {
        $otherCourse = Course::factory()->for($this->owner, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);

        $foreignQuiz = Quiz::factory()->for($otherCourse, 'course')->create(['position' => 1]);

        $this->actingAs($this->owner)
            ->patch(route('instructor.courses.quizzes.update', [$this->course, $foreignQuiz]), [
                'title' => 'Hijacked',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijacked', $foreignQuiz->fresh()->title);
    }

    /**
     * The identifiers each route needs.
     *
     * @return array<string, mixed>
     */
    private function parametersFor(string $route): array
    {
        $parameters = ['course' => $this->course];

        if (str_contains($route, 'modules.lessons') || str_contains($route, '.materials.')) {
            $parameters['module'] = $this->module;
        }

        if (str_contains($route, 'lessons.') || str_contains($route, '.materials.')) {
            $parameters['lesson'] = $this->lesson;
        }

        if (str_contains($route, 'modules.')) {
            $parameters['module'] ??= $this->module;
        }

        if (str_contains($route, '.quizzes.')) {
            $parameters['quiz'] = $this->quiz;
        }

        return $parameters;
    }
}
