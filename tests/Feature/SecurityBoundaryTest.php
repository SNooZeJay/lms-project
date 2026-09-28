<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Tests\TestCase;

/**
 * The boundaries a signed in person must not cross.
 *
 * The interface already hides what a role may not do, which is why these tests
 * exist: a hidden control is a usability decision, not a security control, and it
 * is defeated by typing an address. Every test here sends a real request, with
 * a real session, to a real route, as a user of the wrong role or the wrong
 * owner, and requires the server to refuse.
 *
 * The standard being held to is that authorization lives in the request path
 * and nowhere else. If a check is only in a Blade file or only in JavaScript,
 * deleting it changes nothing that is asserted here.
 */
class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course with a published module, lesson, and a student enrolled.
     *
     * @return array{0: User, 1: User, 2: User, 3: Course, 4: Lesson, 5: Enrollment}
     */
    private function world(): array
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $other, $instructor, $course, $lesson, $enrollment];
    }

    /**
     * A real administrator.
     *
     * The user factory has no administrator state, and its configure() hook
     * gives every plain create() a student profile, so the role has to be set
     * explicitly. Getting this wrong is not a small mistake: an "administrator"
     * that is really a student would make every administrator test pass for the
     * wrong reason.
     */
    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user;
    }

    /* ------------------------------------------------- role boundaries */

    public function test_a_student_cannot_reach_the_administrator_area(): void
    {
        [$student] = $this->world();

        $this->actingAs($student);

        // Typed directly. The navigation never offers these, which is exactly why
        // asking for them has to be the test.
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/activity')->assertForbidden();
        $this->get('/admin/certificates')->assertForbidden();
    }

    public function test_a_student_cannot_reach_the_instructor_area(): void
    {
        [$student] = $this->world();

        $this->actingAs($student);

        $this->get('/instructor')->assertForbidden();
        $this->get('/instructor/courses')->assertForbidden();
        $this->get('/instructor/courses/new')->assertForbidden();
    }

    public function test_an_instructor_cannot_reach_the_administrator_area(): void
    {
        [, , $instructor] = $this->world();

        $this->actingAs($instructor);

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in_rather_than_shown_a_dashboard(): void
    {
        foreach (['/student', '/instructor', '/admin', '/account/profile'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_an_administrator_cannot_open_a_students_workspace_as_themselves(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator);

        // The role is not a ladder. An Administrator is not also a Student, and
        // the student area reads the signed in account's own enrollments, so
        // letting one in would show them an empty page that looks like data.
        $this->get('/student')->assertForbidden();
    }

    /* ------------------------------------------------- privilege escalation */

    public function test_a_student_cannot_change_its_own_role_through_the_admin_route(): void
    {
        [$student] = $this->world();

        $this->actingAs($student)
            ->patch("/admin/users/{$student->id}/role", ['role' => 'administrator'])
            ->assertForbidden();

        $this->assertSame(
            UserRole::Student,
            $student->fresh()->profile->role,
            'A refused request still changed the role.'
        );
    }

    public function test_a_student_cannot_change_another_users_role(): void
    {
        [$student, $other] = $this->world();

        $this->actingAs($student)
            ->patch("/admin/users/{$other->id}/role", ['role' => 'administrator'])
            ->assertForbidden();

        $this->assertSame(UserRole::Student, $other->fresh()->profile->role);
    }

    public function test_a_request_cannot_promote_itself_through_mass_assignment(): void
    {
        [$student] = $this->world();

        // The role lives on the profile, not the user, and is never a validated
        // field. Posting it must change nothing.
        $this->actingAs($student)
            ->patch('/account/profile', [
                'name' => 'Renamed',
                'role' => 'administrator',
                'account_status' => 'active',
            ]);

        $student->refresh();

        $this->assertSame(UserRole::Student, $student->profile->role);
        $this->assertNotSame('administrator', $student->profile->fresh()->role->value);
    }

    public function test_an_instructor_cannot_edit_a_course_they_do_not_own(): void
    {
        [$student, , $instructor, $course] = $this->world();
        $stranger = User::factory()->instructor()->create();

        $this->actingAs($stranger)
            ->get("/instructor/courses/{$course->id}/edit")
            ->assertForbidden();
    }

    /* ------------------------------------------------------------- IDOR */

    public function test_a_student_cannot_open_another_students_enrollment(): void
    {
        [, $other, , , , $enrollment] = $this->world();

        $this->actingAs($other)
            ->get("/student/courses/{$enrollment->course_id}")
            ->assertForbidden();
    }

    public function test_a_student_cannot_read_another_students_certificate(): void
    {
        [$student, $other, , $course, , $enrollment] = $this->world();

        // There is no Certificate factory, and the model's fillable list is a
        // one-field allowlist by design, so mass assignment would silently drop
        // every column below and the insert would fail. forceFill states plainly
        // that this is test scaffolding writing past the allowlist.
        $certificate = new Certificate;
        $certificate->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'certificate_code' => 'SEC-BOUNDARY-0001',
            'student_name_snapshot' => $student->name,
            'course_title_snapshot' => $course->title,
            'completion_date' => now()->toDateString(),
            'status' => CertificateStatus::Issued,
        ])->save();

        $this->actingAs($other)
            ->get("/student/certificates/{$certificate->id}")
            ->assertForbidden();
    }

    public function test_a_student_cannot_complete_a_lesson_for_somebody_elses_enrollment(): void
    {
        [$student, $other, , , $lesson, $enrollment] = $this->world();

        $this->actingAs($other)
            ->post("/student/courses/{$enrollment->course_id}/lessons/{$lesson->id}/complete")
            ->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_a_material_cannot_be_fetched_through_a_mismatched_address(): void
    {
        [$student, $other, , $courseA, $lessonA] = $this->world();

        $moduleB = Module::factory()->create(['course_id' => $courseA->id, 'position' => 2, 'status' => ContentStatus::Published]);
        $lessonB = Lesson::factory()->create(['module_id' => $moduleB->id, 'position' => 1, 'status' => ContentStatus::Published]);

        $courseB = Course::factory()->create([
            'instructor_id' => $courseA->instructor_id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $material = LearningMaterial::factory()->create([
            'lesson_id' => $lessonB->id,
            'storage_disk' => 'local',
            'storage_path' => 'learning-materials/1/probe.pdf',
        ]);

        // The material belongs to lesson B. Asking for it under lesson A's
        // address must not return it, even for someone entitled to the lesson
        // the address names.
        $this->actingAs($student)
            ->get("/student/courses/{$courseA->id}/lessons/{$lessonA->id}/materials/{$material->id}/download")
            ->assertNotFound();
    }

    /* -------------------------------------------- secrets and private files */

    /**
     * PHPUnit 12 removed the docblock form, so the provider is named by
     * attribute. The paths are asserted one at a time so a failure names the
     * exact file rather than "one of thirteen".
     */
    #[DataProvider('sensitivePaths')]
    public function test_a_sensitive_file_cannot_be_fetched_over_http(string $path): void
    {
        $response = $this->get($path);

        // The document root is public/, so a correct application refuses
        // anything outside it. A 200 here would mean the server is serving the
        // project directory. Both 403 and 404 count as refused: a server may
        // legitimately say "forbidden" instead of "not found", and insisting on
        // one particular refusal would only reject a safe implementation.
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            "{$path} was answered with {$response->getStatusCode()} rather than refused."
        );

        $body = (string) $response->getContent();

        foreach (['APP_KEY=', 'DB_PASSWORD=', 'PAYMONGO_SECRET_KEY='] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "{$path} returned configuration.");
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sensitivePaths(): array
    {
        $paths = [
            '/.env',
            '/.env.local',
            '/.env.production',
            '/.env.development',
            '/.env.testing',
            '/.env.backup',
            '/.git/config',
            '/../.env',
            '/storage/logs/laravel.log',
            '/composer.json',
            '/package.json',
            '/artisan',
            '/server.php',
        ];

        $cases = [];

        foreach ($paths as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    public function test_the_web_root_contains_no_dotfiles_or_secrets(): void
    {
        // The strongest version of the check: the document root is enumerated
        // rather than guessed at, so a file added later is caught too.
        $forbidden = ['.env', '.git', 'composer.json', 'package.json', 'artisan', '.htpasswd'];

        foreach (glob(public_path('*')) ?: [] as $entry) {
            $name = basename($entry);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $name,
                    "{$name} is inside the web root and would be served to anyone."
                );
            }
        }

        // And the private disk must not be linked into it.
        $this->assertDirectoryDoesNotExist(
            public_path('storage'),
            'A live public/storage link exposes everything under storage/app/public.'
        );
    }

    public function test_no_route_serves_the_private_disk(): void
    {
        // The private disk holds every Learning Material. Setting 'serve' on a
        // disk makes the framework register GET and PUT routes for any path
        // under it, gated only by a relative signature that APP_KEY computes.
        // The application never mints such a URL, so those routes could only
        // ever serve an attacker who already had the key.
        $this->assertFalse(
            (bool) (config('filesystems.disks.local.serve') ?? false),
            'The private disk is served over HTTP. Turn serve off: MaterialDownloadController is the only path that should return a stored file.'
        );

        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse(
            $routes->contains(fn (string $uri) => str_starts_with($uri, 'storage/')),
            'A route can read or write the private disk directly. '
            .'Found: '.$routes->filter(fn (string $u) => str_starts_with($u, 'storage/'))->implode(', ')
        );
    }

    public function test_the_private_disk_is_outside_the_document_root(): void
    {
        $root = (string) config('filesystems.disks.local.root');

        $this->assertNotEmpty($root);
        $this->assertStringNotContainsString(
            'public',
            str_replace(public_path(), '', $root),
            'The private disk root is inside the document root.'
        );
    }

    public function test_no_environment_value_reaches_a_response(): void
    {
        config([
            'services.paymongo.secret_key' => 'sk_live_this_must_never_be_rendered',
        ]);

        putenv('APP_KEY=base64:shouldneverappearanywhere');
        $_ENV['APP_KEY'] = 'base64:shouldneverappearanywhere';

        foreach (['/', '/login', '/register', '/terms'] as $url) {
            $body = (string) $this->get($url)->getContent();

            $this->assertStringNotContainsString('sk_live_this_must_never_be_rendered', $body);
            $this->assertStringNotContainsString('shouldneverappearanywhere', $body);
        }

        // And the built client bundle must not carry configuration either.
        foreach (glob(public_path('build/assets/*.js')) ?: [] as $bundle) {
            $this->assertStringNotContainsString('sk_live_this_must_never_be_rendered', (string) file_get_contents($bundle));
        }
    }

    /* ----------------------------------------------------- path traversal */

    #[DataProvider('traversalAttempts')]
    public function test_traversal_attempts_never_escape(string $attempt): void
    {
        [$student, , , $course, $lesson] = $this->world();

        $material = LearningMaterial::factory()->create([
            'lesson_id' => $lesson->id,
            'storage_disk' => 'local',
            'storage_path' => 'learning-materials/1/probe.pdf',
        ]);

        $url = "/student/courses/{$course->id}/lessons/{$lesson->id}/materials/{$material->id}/download";

        // The attempt rides in the route segment, which is where a naive
        // implementation would pass it to the filesystem.
        //
        // A malformed address is stopped at one of several layers, and the
        // strongest of them never produces a response at all: Symfony's request
        // parser refuses a backslash or a null byte by throwing before routing
        // begins, the router finds no route, and the handler 404s an id that does
        // not exist. A throw counts as a refusal here, because it means the
        // request never reached application code. Asserting one specific status
        // would make a stricter upstream check look like a regression.
        try {
            $body = (string) $this->actingAs($student)->get($url.$attempt)->getContent();
        } catch (BadRequestException) {
            $this->assertTrue(true, 'The address was refused before it reached the application.');

            return;
        }

        foreach (['APP_KEY', 'DB_PASSWORD', '<?php', 'BEGIN RSA'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "The traversal attempt {$attempt} returned configuration.");
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalAttempts(): array
    {
        return [
            'parent' => ['/../../../.env'],
            'encoded parent' => ['/%2e%2e%2f%2e%2e%2f.env'],
            'double encoded' => ['/%252e%252e%252f.env'],
            'backslash' => ['/..\\..\\..\\.env'],
            'null byte' => ['/%00.env'],
            'absolute' => ['//etc/passwd'],
            'dot segment' => ['/./../../.env'],
        ];
    }

    public function test_a_stored_path_is_never_taken_from_the_request(): void
    {
        [$student, , , $course, $lesson] = $this->world();

        $material = LearningMaterial::factory()->create([
            'lesson_id' => $lesson->id,
            'storage_disk' => 'local',
            'storage_path' => 'learning-materials/1/probe.pdf',
        ]);

        // Offering the path as a query parameter must be ignored outright. The
        // stored record is the only source of where a file lives.
        $response = $this->actingAs($student)->get(
            "/student/courses/{$course->id}/lessons/{$lesson->id}/materials/{$material->id}/download?path=../../../../.env"
        );

        $this->assertStringNotContainsString('APP_KEY', (string) $response->getContent());
    }

    /* --------------------------------------------------------------- XSS */

    public function test_stored_content_is_escaped_rather_than_rendered(): void
    {
        $instructor = User::factory()->instructor()->create();

        $payload = '<script>window.__pwned=1</script>';
        $attributePayload = '"><script>window.__pwned=2</script>';

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'title' => 'Safe title',
            'description' => $payload,
        ]);

        $body = (string) $this->get("/courses/{$course->slug}")->getContent();

        // Blade escapes by default, so the payload must appear as visible text
        // and never as a live script element. The escaped form is asserted too:
        // without it a template that dropped the content entirely would pass,
        // and dropping content is not the same as encoding it.
        $this->assertStringNotContainsString('<script>window.__pwned', $body);
        $this->assertStringNotContainsString($payload, $body);
        $this->assertStringContainsString('&lt;script&gt;window.__pwned=1', $body);

        // A name is the one field a person can set about themselves and then
        // see rendered on several pages, so it is the most likely place for an
        // attribute-breaking payload to land. The name lives on users, not on
        // profiles, which is the distinction that broke the first attempt.
        $student = User::factory()->create(['name' => $attributePayload]);

        $dashboard = (string) $this->actingAs($student)->get('/account/profile')->getContent();

        $this->assertStringNotContainsString('<script>window.__pwned=2', $dashboard);
        $this->assertStringNotContainsString('"><script', $dashboard);
    }

    public function test_a_search_term_is_escaped_in_the_response(): void
    {
        $body = (string) $this->get('/courses?search='.urlencode('<script>alert(1)</script>'))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $body);
    }

    /* ------------------------------------------------------------ sessions */

    public function test_the_session_cookie_is_hardened(): void
    {
        $response = $this->get('/login');

        $cookies = collect($response->headers->getCookies())
            ->filter(fn ($cookie) => str_contains((string) $cookie->getName(), 'session'));

        $this->assertNotEmpty($cookies, 'The login page set no session cookie.');

        foreach ($cookies as $cookie) {
            // HttpOnly keeps the cookie away from script, and SameSite keeps it
            // off a cross site POST.
            $this->assertTrue($cookie->isHttpOnly(), 'The session cookie is not HttpOnly.');
            $this->assertSame('lax', $cookie->getSameSite(), 'The session cookie SameSite is not lax.');
        }
    }

    public function test_the_session_cookie_is_secure_when_the_public_address_is_https(): void
    {
        // The flag is not read from configuration, it is decided at request time
        // by SecureSessionCookies from the public scheme. Asserting the
        // configuration value instead would have tested a setting that is false
        // by design and proved nothing about the cookie that is actually sent.
        $this->assertFalse(
            (bool) config('session.secure'),
            'The test environment is http, so the secure flag is expected to start off.'
        );

        config(['app.url' => 'https://lms.example.test']);

        $cookies = collect($this->get('/login')->headers->getCookies())
            ->filter(fn ($cookie) => str_contains((string) $cookie->getName(), 'session'));

        $this->assertNotEmpty($cookies);

        foreach ($cookies as $cookie) {
            $this->assertTrue(
                $cookie->isSecure(),
                'The session cookie is not secure although the public address is https. A stolen cookie would be replayable over plain http.'
            );
        }
    }

    public function test_the_session_cookie_is_configured_http_only(): void
    {
        $this->assertTrue(
            (bool) config('session.http_only'),
            'session.http_only is the default every cookie inherits, and turning it off would expose the session to script.'
        );
    }

    public function test_signing_out_invalidates_the_session(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/student')->assertOk();

        $this->post('/logout');

        $this->assertGuest();
    }

    /* ------------------------------------------------------------ headers */

    public function test_every_response_carries_the_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("base-uri 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_the_content_security_policy_has_no_unsafe_inline_script(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        // unsafe-inline in script-src hands back exactly the permission a nonce
        // exists to withhold, which is the whole reason for using one.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-/", $policy);
    }

    public function test_the_php_version_is_not_disclosed(): void
    {
        // The application must never add the header itself. This is the half of
        // the boundary that lives in code and is therefore assertable here.
        $this->assertNull(
            $this->get('/')->headers->get('X-Powered-By'),
            'The application published a PHP signature.'
        );
    }

    public function test_the_php_signature_is_removed_at_the_sapi_level(): void
    {
        // The other half is that PHP itself sends X-Powered-By before the
        // framework builds a response, so clearing it only from the Response
        // object is not enough. That is a property of the running server and
        // cannot be observed from a CLI test, because the CLI SAPI never emits
        // the header in the first place; a green run here proves nothing about
        // the live address, which is exactly why it is worth stating.
        //
        // What is assertable is that the code performs the SAPI-level removal
        // and that the operational half is tracked rather than assumed.
        $source = (string) file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));

        $this->assertStringContainsString(
            "header_remove('X-Powered-By')",
            $source,
            'The middleware must clear the header PHP recorded, not only the copy on the response.'
        );

        $advice = $this->readinessAdvice();

        $this->assertNotEmpty($advice, 'The readiness command says nothing about expose_php, so the durable fix is untracked.');

        $this->assertTrue(
            collect($advice)->contains(fn (string $line) => str_contains($line, 'expose_php=Off')),
            'The readiness command does not name expose_php=Off as the fix. Found: '.implode(' | ', $advice)
        );
    }

    /**
     * Every remediation string the readiness command is able to print that
     * mentions expose_php.
     *
     * @return list<string>
     */
    private function readinessAdvice(): array
    {
        $command = (string) file_get_contents(app_path('Console/Commands/CheckProductionReadiness.php'));

        preg_match_all("/'([^']*expose_php[^']*)'/i", $command, $matches);

        return $matches[1] ?? [];
    }
}
