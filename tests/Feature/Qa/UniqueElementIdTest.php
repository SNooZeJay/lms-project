<?php

namespace Tests\Feature\Qa;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * No two elements on a page may claim the same id.
 *
 * This was found by walking every page in a real browser as every role and
 * counting identifiers, not by reading the views. The Instructor course outline
 * page carried `field-title` nineteen times, because the form component derives
 * its identifier from the field name alone and that page renders an add form for
 * every module, every lesson and every material. A browser measurement that is
 * not repeated by a test is a claim that quietly stops being true.
 *
 * The cost is not cosmetic. A `<label for="field-title">` points at the first
 * element with that identifier, so every later field is announced with the wrong
 * name, and any script that looks the field up by identifier edits or reads the
 * first one instead. On a page whose whole purpose is editing nineteen
 * separate things, that is the difference between a screen reader being usable
 * and not.
 */
class UniqueElementIdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function publicPages(): array
    {
        $cases = [];

        foreach ([
            'home' => '/',
            'login' => '/login',
            'register' => '/register',
            'forgot password' => '/forgot-password',
            'catalog' => '/courses',
            'terms' => '/terms',
            'privacy' => '/privacy',
        ] as $label => $path) {
            $cases[$label] = [$path];
        }

        return $cases;
    }

    #[DataProvider('publicPages')]
    public function test_a_public_page_repeats_no_identifier(string $path): void
    {
        $this->assertNoRepeatedIds($this->get($path), $path);
    }

    public function test_the_instructor_outline_page_repeats_no_identifier(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        // More than one of each, because a single module or lesson produces one
        // form and cannot show the problem. Two is the smallest number that does.
        //
        // The position is set explicitly. Both factories default to 1, and
        // (course_id, position) is unique, so leaving it alone would fail on the
        // fixture rather than on the thing being tested.
        foreach ([1, 2] as $modulePosition) {
            $module = Module::factory()->for($course, 'course')->withPosition($modulePosition)->create();

            foreach ([1, 2] as $lessonPosition) {
                Lesson::factory()->for($module, 'module')->create(['position' => $lessonPosition]);
            }
        }

        /*
         | Quizzes too, and more than one.
         |
         | The page also renders an add-question form per quiz. A first version of
         | this test built only modules and lessons, passed, and still shipped a
         | page with field-prompt repeated once per quiz, because the browser
         | measurement that found the original defect had used a course that
         | happened to have quizzes and the test had not. A test that only builds
         | the data it thought of is a test that only covers the data it thought
         | of.
         */
        // The position is set explicitly here too: the factory defaults to 1 and
        // (course_id, position) is unique on quizzes as well.
        foreach ([1, 2] as $quizPosition) {
            Quiz::factory()->for($course, 'course')->create([
                'title' => 'Quiz '.$quizPosition,
                'position' => $quizPosition,
            ]);
        }

        $this->assertNoRepeatedIds(
            $this->actingAs($instructor)->get("/instructor/courses/{$course->id}"),
            '/instructor/courses/{id}'
        );
    }

    public function test_the_instructor_workspace_repeats_no_identifier(): void
    {
        $instructor = User::factory()->instructor()->create();

        Course::factory()->for($instructor, 'instructor')->create();

        foreach ([
            '/instructor',
            '/instructor/courses',
            '/instructor/courses/new',
        ] as $path) {
            $this->assertNoRepeatedIds($this->actingAs($instructor)->get($path), $path);
        }
    }

    public function test_the_administrator_workspace_repeats_no_identifier(): void
    {
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        foreach (['/admin', '/admin/users', '/admin/reports', '/admin/activity'] as $path) {
            $this->assertNoRepeatedIds($this->actingAs($administrator)->get($path), $path);
        }
    }

    /**
     * Every label must point at an element that exists, and at only one.
     *
     * A `for` naming nothing is a label attached to nothing. A `for` naming a
     * repeated identifier is the failure this whole class exists for, seen from
     * the other side.
     */
    public function test_every_label_points_at_exactly_one_element(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create();
        Lesson::factory()->for($module, 'module')->create();

        // Two quizzes, so the add-question form repeats and the labels have to be
        // told apart rather than merely counted.
        foreach ([1, 2] as $quizPosition) {
            Quiz::factory()->for($course, 'course')->create([
                'title' => 'Quiz '.$quizPosition,
                'position' => $quizPosition,
            ]);
        }

        $body = (string) $this->actingAs($instructor)->get("/instructor/courses/{$course->id}")->getContent();

        preg_match_all('/<label\b[^>]*\bfor="([^"]+)"/i', $body, $matches);

        $this->assertNotEmpty($matches[1], 'The outline page rendered no labels, so this test proved nothing.');

        foreach (array_unique($matches[1]) as $target) {
            $count = preg_match_all('/\bid="'.preg_quote($target, '/').'"/i', $body);

            $this->assertSame(
                1,
                $count,
                "A label points at \"{$target}\", which matches {$count} elements. It can only ever describe one of them."
            );
        }
    }

    private function assertNoRepeatedIds($response, string $label): void
    {
        $response->assertOk();

        $body = (string) $response->getContent();

        preg_match_all('/\bid="([^"]+)"/i', $body, $matches);

        $seen = [];

        foreach ($matches[1] as $id) {
            $seen[$id] = ($seen[$id] ?? 0) + 1;
        }

        $repeated = array_filter($seen, fn (int $count): bool => $count > 1);

        $this->assertSame(
            [],
            $repeated,
            "{$label} repeats these identifiers: ".implode(', ', array_map(
                fn (string $id, int $count): string => "{$id} x{$count}",
                array_keys($repeated),
                $repeated
            ))
        );
    }
}
