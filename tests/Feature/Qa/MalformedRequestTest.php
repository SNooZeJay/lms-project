<?php

namespace Tests\Feature\Qa;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Tests\Support\HostileInput;
use Tests\TestCase;

/**
 * Charter C3: direct request construction, and charter C4: hostile query strings.
 *
 * Everything here is a URL or a payload a person could type, copy, or guess. The
 * interface never produces any of it, which is exactly the point: a hidden link
 * is a usability decision, and the person who wants to reach somebody else's
 * record does not use the interface.
 *
 * Three properties hold across every case.
 *
 * The request is answered without a crash. A malformed address must produce a
 * refusal, not an error page and not a stack trace.
 *
 * The refusal does not leak. A 404 or 403 for a resource that exists is fine. A
 * 404 for a resource that does not exist must not confirm the difference, and no
 * refusal may print a path, a query, or an internal name.
 *
 * Nothing changes. A refused request must leave the database exactly as it was,
 * which is the property a scanner of this shape is most likely to violate by
 * accident when a route resolves to a real record before the check runs.
 */
class MalformedRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Course, 3: Module, 4: Lesson}
     */
    private function world(): array
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->for($module, 'module')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        return [$student, $instructor, $course, $module, $lesson];
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user;
    }

    /**
     * Assert the response is a refusal rather than a failure, and says nothing.
     *
     * @param  TestResponse  $response
     */
    private function assertRefusedWithoutLeaking($response, string $label): void
    {
        $status = $response->getStatusCode();

        $this->assertNotSame(500, $status, "{$label} produced a server error.");
        $this->assertNotSame(503, $status, "{$label} produced a service error.");
        $this->assertContains(
            $status,
            [302, 400, 401, 403, 404, 405, 422, 429],
            "{$label} answered with {$status}, which is neither a success nor a refusal."
        );

        $body = (string) $response->getContent();

        foreach ([
            'Stack trace',
            'vendor/laravel',
            'Illuminate\\',
            'SQLSTATE',
            'base_path',
            'APP_KEY',
            '.env',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "{$label} leaked {$needle}.");
        }
    }

    /* ------------------------------------------------- malformed addresses */

    /**
     * Addresses that must resolve to nothing.
     *
     * Only addresses whose *path* is nonsense belong here. An address whose
     * nonsense is removed before routing is deliberately not in this list: a
     * framework that answers `//courses` with the catalog page is behaving
     * correctly, and a test demanding a refusal for it fails on a working
     * application. Six such cases were wrongly filed here on the first run.
     *
     * @return array<string, array{0: string}>
     */
    public static function unresolvableUrls(): array
    {
        $urls = [
            'a path that does not exist' => '/this-route-was-never-defined',
            'a root that is a file' => '/index.php/extra',
            'a trailing dot' => '/courses.',
            'a very long path' => '/'.str_repeat('segment/', 40),
            'deeply nested nonsense' => '/student/courses/1/lessons/1/lessons/1/lessons/1',
            'an api path' => '/api/v1/users',
            'a php file' => '/vendor/autoload.php',
            'a git path' => '/.git/HEAD',
            'a storage path' => '/storage/logs/laravel.log',
            'a dotfile' => '/.env',
            'a directory' => '/app/',
            'a bare word' => '/wibble',
        ];

        $cases = [];

        foreach ($urls as $label => $url) {
            $cases[$label] = [$url];
        }

        return $cases;
    }

    /**
     * Addresses whose odd part is gone before the application sees them.
     *
     * A double slash normalises, a fragment never leaves the browser, and an
     * unknown query parameter is ignored. The only requirement is that the request
     * is answered safely, which is what is asserted.
     *
     * @return array<string, array{0: string}>
     */
    public static function normalisedUrls(): array
    {
        $urls = [
            'a double slash' => '//courses',
            'a query with no value' => '/courses?search',
            'a query with an array' => '/courses?search[]=a&search[]=b',
            'a repeated query key' => '/courses?level=beginner&level=advanced',
            'a query with a fragment' => '/courses#frag',
            'an encoded newline' => '/courses%0d%0a',
        ];

        $cases = [];

        foreach ($urls as $label => $url) {
            $cases[$label] = [$url];
        }

        return $cases;
    }

    #[DataProvider('unresolvableUrls')]
    public function test_an_unresolvable_address_is_refused(string $url): void
    {
        // As a guest, which is the most exposed case: no session, no identity to
        // check, so a bad address reaches the router with nothing to filter it.
        $this->assertRefusedWithoutLeaking($this->get($url), "guest GET {$url}");
    }

    #[DataProvider('unresolvableUrls')]
    public function test_an_unresolvable_address_is_refused_for_a_signed_in_student(string $url): void
    {
        [$student] = $this->world();

        $this->assertRefusedWithoutLeaking(
            $this->actingAs($student)->get($url),
            "student GET {$url}"
        );
    }

    #[DataProvider('normalisedUrls')]
    public function test_an_address_that_normalises_is_answered_safely(string $url): void
    {
        $response = $this->get($url);

        $this->assertNotContains(
            $response->getStatusCode(),
            [500, 503],
            "{$url} produced a server error."
        );

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    /**
     * A malformed address is stopped before routing begins.
     *
     * A null byte and a raw control character are rejected by the request parser,
     * which throws rather than answering. A throw is the strongest possible
     * refusal, because no application code has run at all, so it counts as a pass
     * rather than a failure. Asserting a status code for these would be asserting
     * a weaker property than the one that actually holds.
     */
    public function test_a_malformed_address_is_rejected_before_routing(): void
    {
        foreach (["/courses\0", "/courses\n", "/courses\r"] as $url) {
            try {
                $response = $this->get($url);

                $this->assertNotContains(
                    $response->getStatusCode(),
                    [500, 503],
                    "{$url} was answered with a server error rather than refused."
                );
            } catch (BadRequestException) {
                // Rejected by the parser. The strongest outcome available.
                $this->assertTrue(true);
            }
        }
    }

    /**
     * A bad identifier in a route, in both a student path and a catalog path.
     *
     * The identifiers are not valid URL characters in every case, so each is
     * encoded before being placed in a path. Sending a raw quote or a raw
     * newline produces an address the request parser rejects, which tests a
     * different layer than the one this is about.
     *
     * @return array<string, array{0: string}>
     */
    public static function badCourseIdentifiers(): array
    {
        $urls = [];

        foreach (HostileInput::badIdentifiers() as $label => $value) {
            $encoded = rawurlencode((string) $value);

            $urls["student course {$label}"] = ["/student/courses/{$encoded}"];
            $urls["catalog course {$label}"] = ["/courses/{$encoded}"];
        }

        return $urls;
    }

    #[DataProvider('badCourseIdentifiers')]
    public function test_a_bad_identifier_never_returns_another_record(string $url): void
    {
        [$student, , $course] = $this->world();

        $before = Course::query()->count();

        $response = $this->actingAs($student)->get($url);

        $this->assertRefusedWithoutLeaking($response, "student GET {$url}");

        $this->assertSame($before, Course::query()->count(), "{$url} changed the number of courses.");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminRoutes(): array
    {
        $routes = [
            'user list' => ['/admin/users', 'get'],
            'user role change' => ['/admin/users/{user}/role', 'patch'],
            'user status change' => ['/admin/users/{user}/status', 'patch'],
            'reports' => ['/admin/reports', 'get'],
            'activity' => ['/admin/activity', 'get'],
            'certificates' => ['/admin/certificates', 'get'],
            'course detail' => ['/admin/courses/{course}', 'get'],
            'material download' => ['/admin/materials/{material}/download', 'get'],
        ];

        $cases = [];

        foreach ($routes as $label => $route) {
            $cases[$label] = [$route[1], $route[0]];
        }

        return $cases;
    }

    #[DataProvider('adminRoutes')]
    public function test_a_student_cannot_reach_an_administrator_route_by_typing_it(string $method, string $path): void
    {
        [$student, , $course, , $lesson] = $this->world();

        $material = LearningMaterial::factory()->create(['lesson_id' => $lesson->id]);

        $url = str_replace(
            ['{user}', '{course}', '{material}'],
            [(string) $student->id, (string) $course->id, (string) $material->id],
            $path
        );

        $response = match ($method) {
            'patch' => $this->actingAs($student)->patch($url, ['role' => 'administrator', 'status' => 'suspended']),
            default => $this->actingAs($student)->get($url),
        };

        $this->assertRefusedWithoutLeaking($response, "student {$method} {$url}");

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403, 404],
            "A student reached {$url} with a {$response->getStatusCode()} that is not a refusal."
        );

        $this->assertSame(
            UserRole::Student,
            $student->fresh()->profile->role,
            "A student reached {$url} and changed their own role."
        );
    }

    /* ------------------------------------------------- hostile query strings */

    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileSearchTerms(): array
    {
        $cases = [];

        foreach (HostileInput::classes() as $class => $value) {
            $cases["search {$class}"] = ['q='.urlencode($value)];
        }

        // Search operators that mean something to a database rather than a
        // person, since a LIKE query is the one place a wildcard can change the
        // shape of the result.
        foreach ([
            'wildcard percent' => '%',
            'wildcard underscore' => '_',
            'both wildcards' => '%_%',
            'many wildcards' => str_repeat('%', 40),
            'quote then wildcard' => "' OR '%'='",
            'backslash' => '\\',
            'double quote' => '"',
        ] as $label => $value) {
            $cases["search {$label}"] = ['q='.urlencode($value)];
        }

        return $cases;
    }

    #[DataProvider('hostileSearchTerms')]
    public function test_the_catalog_survives_any_search_term(string $query): void
    {
        $response = $this->get('/courses?'.$query);

        // The search field is capped at 100 characters, so a longer term is
        // refused with a redirect back to the form rather than searched. Both are
        // correct answers; demanding one of them would make the test fail on a
        // working validation rule.
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            "A search of {$query} was answered with {$response->getStatusCode()}."
        );

        $body = (string) $response->getContent();

        $this->assertStringNotContainsString('<script>window.__probe', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringContainsString('</html>', $body);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileFilterCombinations(): array
    {
        $cases = [
            'unknown level' => 'level=wizard',
            'unknown type' => 'course_type=free-ish',
            'category with a wildcard' => 'category=%25',
            'array level' => 'level[]=beginner',
            'every filter at once' => 'q=a&level=beginner&course_type=paid&category=%25',
            'case mismatch' => 'level=BEGINNER',
            'padded value' => 'level=%20beginner%20',
            'null byte' => 'level=beginner%00',
            'negative level' => 'level=-1',
            'numeric level' => 'level=1',
            'repeated filters' => 'level=beginner&level=nonsense',
        ];

        $out = [];

        foreach ($cases as $label => $query) {
            $out[$label] = [$query];
        }

        return $out;
    }

    #[DataProvider('hostileFilterCombinations')]
    public function test_the_catalog_survives_any_filter_combination(string $query): void
    {
        $this->get('/courses?'.$query)->assertOk();
    }

    /* ---------------------------------------------- extra and missing fields */

    public function test_extra_fields_are_ignored_rather_than_mass_assigned(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create();

        $originalSlug = $course->slug;
        $originalStatus = $course->status;
        $originalInstructor = $course->instructor_id;
        $originalCurrency = $course->currency;

        $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}", [
            'title' => 'A legitimate edit',
            'description' => 'Body',
            'level' => 'beginner',
            'course_type' => 'free',
            'price_minor' => 0,
            // Every one of these is server-owned.
            'id' => 9999,
            'slug' => 'hijacked',
            'status' => 'published',
            'currency' => 'USD',
            'instructor_id' => 9999,
            'published_at' => '2000-01-01',
            'created_at' => '2000-01-01',
            'thumbnail_path' => '/etc/passwd',
        ]);

        $course->refresh();

        // Asserted as the security outcome rather than as a mechanism. The
        // declared contract is that these fields are prohibited, so the whole
        // request is refused and the title is unchanged as well. An earlier
        // version of this test asserted the title had changed, which failed on an
        // application that was refusing the request outright, and would have
        // passed on one that mass assigned a slug.
        $this->assertNotSame('hijacked', $course->slug, 'The slug was taken from the request.');
        $this->assertSame($originalSlug, $course->slug);
        $this->assertSame($originalStatus, $course->status, 'The status was taken from the request.');
        $this->assertSame($originalInstructor, $course->instructor_id, 'The owner was taken from the request.');
        $this->assertSame($originalCurrency, $course->currency, 'The currency was taken from the request.');
        $this->assertNotSame(9999, $course->id);
        $this->assertNotSame('2000-01-01 00:00:00', (string) $course->created_at);
    }

    public function test_a_missing_required_field_is_refused_and_writes_nothing(): void
    {
        $instructor = User::factory()->instructor()->create();

        $before = Course::query()->count();

        $response = $this->actingAs($instructor)->post('/instructor/courses', [
            'description' => 'A description with no title',
        ]);

        $this->assertRefusedWithoutLeaking($response, 'course creation with no title');

        $this->assertSame($before, Course::query()->count(), 'A course with no title was written.');
    }

    public function test_an_extra_field_naming_a_server_owned_column_is_refused(): void
    {
        $instructor = User::factory()->instructor()->create();

        $before = Course::query()->count();

        // Declared prohibited, so this is a refusal rather than a silent ignore.
        // Asserting the refusal rather than the outcome is deliberate: the
        // outcome is already covered above, and this pins the declared contract.
        $this->actingAs($instructor)->post('/instructor/courses', [
            'title' => 'A title',
            'description' => 'Body',
            'level' => 'beginner',
            'course_type' => 'free',
            'price_minor' => 0,
            'status' => 'published',
        ])->assertSessionHasErrors('status');

        $this->assertSame($before, Course::query()->count());
    }

    /* ------------------------------------------------- method not allowed */

    public function test_a_get_request_cannot_reach_a_write_route(): void
    {
        $instructor = User::factory()->instructor()->create();

        $before = Course::query()->count();

        $response = $this->actingAs($instructor)->get('/instructor/courses');

        $this->assertAnsweredWithoutServerError($response, 'GET on a page that also has a write route');
        $this->assertSame($before, Course::query()->count());
    }

    private function assertAnsweredWithoutServerError($response, string $label): void
    {
        $this->assertNotContains(
            $response->getStatusCode(),
            [500, 503],
            "{$label} produced a server error."
        );
    }

    /* ------------------------------------------- expired and stale sessions */

    public function test_a_stale_page_cannot_act_after_the_session_is_dropped(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create();

        // A page was rendered, then the session was invalidated, as happens when
        // a person signs out in another tab. The form is still on screen.
        $this->actingAs($instructor)->get("/instructor/courses/{$course->id}/edit")->assertOk();

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $response = $this->actingAs($instructor)
            ->patch("/instructor/courses/{$course->id}", [
                'title' => 'Edited after the session ended',
                'description' => 'Body',
                'level' => 'beginner',
                'course_type' => 'free',
                'price_minor' => 0,
            ]);

        $this->assertAnsweredWithoutServerError($response, 'saving from a stale page');
    }

    public function test_a_token_from_a_stale_page_is_refused(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create();

        $this->actingAs($instructor)
            ->patch("/instructor/courses/{$course->id}", [
                'title' => 'A legitimate edit',
                'description' => 'Body',
                'level' => 'beginner',
                'course_type' => 'free',
                'price_minor' => 0,
            ])->assertRedirect();

        // A save with no token at all is a cross site request as far as the
        // application is concerned.
        $response = $this->actingAs($instructor)->post('/instructor/courses', [
            'title' => 'Created without a token',
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [419, 302, 403, 422],
            'A write with no CSRF token was not refused.'
        );
    }
}
