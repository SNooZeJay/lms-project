<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    // These tests create published courses, so the database has to be reset
    // between them. Without it the rows survive into the next test file and
    // break any test that expects an empty catalog.
    use RefreshDatabase;

    public function test_home_page_renders_the_public_shell(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('IT Learning Hub')
            ->assertSee('Learn IT. Build practical skills.')
            ->assertSee('Browse courses')
            ->assertSee('Sign in')
            ->assertSee('Skip to main content')
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-brand-mark', false);
    }

    public function test_the_home_page_copy_does_not_explain_how_the_software_works(): void
    {
        // The hero used to talk about records and stored state, which is
        // documentation rather than something a student needs in order to use
        // the site. These are the phrases that started it.
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        foreach ([
            'in one record',
            'course record',
            'reconstruct where they stand',
            'the learning path',
            'An Administrator assigns every role',
        ] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $body);
        }
    }

    public function test_the_home_page_offers_no_marketeting_filler(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        foreach ([
            'Unlock your potential',
            'Embark on your learning journey',
            'Empower yourself',
            'Take your skills to the next level',
            'Transform your future',
            'Seamlessly',
            'Cutting-edge',
            'Learn, grow, and succeed',
            'Whether you',
            'Designed to empower',
            'Unlock endless possibilities',
        ] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $body);
        }
    }

    public function test_the_home_page_shows_real_published_courses(): void
    {
        $free = Course::factory()->create([
            'title' => 'Introduction to Information Technology',
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $paid = Course::factory()->create([
            'title' => 'Programming Fundamentals with Python',
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 120000,
        ]);

        $response = $this->get(route('home'))->assertOk();

        // The catalog is the point of the page, so a real course has to appear
        // on it rather than only behind the catalog link.
        $response->assertSee($free->title);
        $response->assertSee($paid->title);
        $response->assertSee(route('courses.show', $free));
        $response->assertSee(route('courses.show', $paid));
    }

    public function test_the_home_page_never_shows_an_unpublished_course(): void
    {
        $draft = Course::factory()->create([
            'title' => 'Draft Course That Should Not Appear',
            'status' => CourseStatus::Draft,
        ]);

        $archived = Course::factory()->create([
            'title' => 'Archived Course That Should Not Appear',
            'status' => CourseStatus::Archived,
        ]);

        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString($draft->title, $body);
        $this->assertStringNotContainsString($archived->title, $body);
    }

    public function test_the_home_page_does_not_offer_registration(): void
    {
        // Registration is reached from the sign in page. A guest who cannot sign
        // in yet should not be pushed to register from every public page, and a
        // call to action on a landing page is the easiest thing to add by
        // reflex, so this is pinned deliberately.
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Create student account', $body);
        $this->assertStringNotContainsString('Create a student account', $body);
        $this->assertStringNotContainsString(route('register'), $body);
    }

    public function test_home_page_links_only_to_routes_that_exist(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        // Every link on the public home page must be a real address. A dead link
        // on a landing page is the most visible way to look unfinished, so each
        // anchor is compared with the approved public routes. Only anchors are
        // read, because the stylesheet and the favicon are assets, not pages.
        preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/', $body, $matches);

        $base = rtrim(config('app.url'), '/');

        // The approved public pages, written as paths so a link is compared by
        // where it goes rather than by how the address was built.
        $approved = [
            '/' => true,
            '/login' => true,
            '/courses' => true,
            '/terms' => true,
            '/privacy' => true,
        ];

        $checked = 0;

        foreach (array_unique($matches[1]) as $href) {
            if (! str_starts_with($href, $base)) {
                continue;
            }

            // A query string is a filter on the same page, not a different page,
            // so it is dropped before the address is compared.
            $path = (string) parse_url($href, PHP_URL_PATH);
            $path = rtrim(str_replace($base, '', $path), '/');

            $isCoursePage = $path !== ''
                && str_starts_with($path, '/courses/')
                && preg_match('#^/courses/[A-Za-z0-9-]+$#', $path) === 1;

            $this->assertTrue(
                array_key_exists($path === '' ? '/' : $path, $approved) || $isCoursePage,
                'The home page links to an address that is not an approved public route: '.$href,
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'The home page should link to at least one internal address.');
    }
}
