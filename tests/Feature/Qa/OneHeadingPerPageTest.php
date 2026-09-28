<?php

namespace Tests\Feature\Qa;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exactly one page heading per page, on every page.
 *
 * The project audit states this as a verified property. It was verified by a
 * browser measurement that covered the workspace and catalog pages but not the
 * sign in, register, and password pages, and the three auth pages each carried
 * two h1 elements: the marketing line in the decorative panel and the form's own
 * heading. A browser measurement that is not repeated by a test is a claim that
 * quietly stops being true, so this is the test that keeps it true.
 *
 * A page heading is what a screen reader announces when a page opens, so a
 * second one means the person hears a slogan before they hear what the page is
 * for.
 */
class OneHeadingPerPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every public page, as a guest.
     *
     * @return array<string, array{0: string}>
     */
    public static function publicPages(): array
    {
        $paths = [
            'home' => '/',
            'login' => '/login',
            'register' => '/register',
            'forgot password' => '/forgot-password',
            'catalog' => '/courses',
            'terms' => '/terms',
            'privacy' => '/privacy',
        ];

        $cases = [];

        foreach ($paths as $label => $path) {
            $cases[$label] = [$path];
        }

        return $cases;
    }

    #[DataProvider('publicPages')]
    public function test_a_public_page_has_exactly_one_heading(string $path): void
    {
        $this->assertOneHeading($this->get($path), $path);
    }

    public function test_a_published_course_page_has_exactly_one_heading(): void
    {
        $course = Course::factory()->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $this->assertOneHeading($this->get("/courses/{$course->slug}"), '/courses/{slug}');
    }

    public function test_a_draft_course_page_is_refused_and_leaks_no_heading(): void
    {
        $course = Course::factory()->create(['status' => CourseStatus::Draft]);

        $this->get("/courses/{$course->slug}")->assertNotFound();
    }

    public function test_a_workspace_page_has_exactly_one_heading(): void
    {
        $student = User::factory()->create();

        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        foreach ([
            '/student',
            '/student/courses',
            '/student/certificates',
            '/account/profile',
        ] as $path) {
            $this->assertOneHeading($this->actingAs($student)->get($path), $path);
        }
    }

    public function test_the_instructor_workspace_has_exactly_one_heading(): void
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create();

        foreach (['/instructor', '/instructor/courses', '/instructor/courses/new'] as $path) {
            $this->assertOneHeading($this->actingAs($instructor)->get($path), $path);
        }
    }

    public function test_the_administrator_workspace_has_exactly_one_heading(): void
    {
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        foreach (['/admin', '/admin/users', '/admin/reports', '/admin/activity', '/admin/certificates'] as $path) {
            $this->assertOneHeading($this->actingAs($administrator)->get($path), $path);
        }
    }

    /**
     * The decorative panel on the auth pages may not claim the page heading.
     *
     * The panel keeps its accessible name through aria-labelledby, which accepts
     * any element, so demoting the line to a paragraph costs the region nothing
     * and gives the form heading back its place.
     */
    public function test_the_auth_panel_keeps_its_accessible_name_without_being_a_heading(): void
    {
        $body = (string) $this->get('/login')->getContent();

        $this->assertStringContainsString('aria-labelledby="auth-message"', $body);
        $this->assertStringContainsString('id="auth-message"', $body);

        $this->assertDoesNotMatchRegularExpression(
            '/<h1[^>]*id="auth-message"/',
            $body,
            'The decorative panel is a page heading again, so the auth pages have two.'
        );
    }

    /**
     * @param  TestResponse  $response
     */
    private function assertOneHeading($response, string $label): void
    {
        $response->assertOk();

        $count = preg_match_all('/<h1\b/i', (string) $response->getContent());

        $this->assertSame(
            1,
            $count,
            "{$label} has {$count} h1 elements. The project standard is exactly one page heading."
        );
    }
}
