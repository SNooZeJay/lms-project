<?php

namespace Tests\Feature\Phase5F;

use App\Enums\ContentStatus;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicCourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_published_courses_in_the_catalog(): void
    {
        $published = $this->makePublishedCourse(['title' => 'Networking Basics']);

        $this->get('/courses')
            ->assertOk()
            ->assertSee('Networking Basics')
            ->assertSee(route('courses.show', $published), false);
    }

    public function test_catalog_never_lists_draft_or_archived_courses(): void
    {
        $this->makePublishedCourse(['title' => 'Visible Course']);

        $draft = Course::factory()->create([
            'title' => 'Hidden Draft Course',
            'status' => CourseStatus::Draft,
        ]);
        $archived = Course::factory()->create([
            'title' => 'Hidden Archived Course',
            'status' => CourseStatus::Archived,
        ]);

        $this->get('/courses')
            ->assertOk()
            ->assertSee('Visible Course')
            ->assertDontSee('Hidden Draft Course')
            ->assertDontSee('Hidden Archived Course');

        $this->get("/courses/{$draft->slug}")->assertNotFound();
        $this->get("/courses/{$archived->slug}")->assertNotFound();
    }

    public function test_course_details_show_public_metadata_and_outline_structure(): void
    {
        $course = $this->makePublishedCourse([
            'title' => 'Web Fundamentals',
            'description' => 'Learn how the web works.',
            'learning_objectives' => 'Describe HTTP and HTML.',
            'category' => 'Web',
            'level' => CourseLevel::Beginner,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);
        $module = Module::factory()->for($course, 'course')->create([
            'title' => 'Module One',
            'position' => 2,
            'status' => ContentStatus::Published,
        ]);
        $lesson = Lesson::factory()->for($module, 'module')->create([
            'title' => 'How HTTP Works',
            'summary' => 'A short summary.',
            'content_text' => 'SECRET LESSON CONTENT',
            'estimated_minutes' => 25,
            'is_required' => true,
            'status' => ContentStatus::Published,
        ]);
        LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'SECRET MATERIAL TITLE',
            'content_text' => 'SECRET MATERIAL CONTENT',
            'external_url' => 'https://secret.example.com/file',
        ]);

        $this->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Web Fundamentals')
            ->assertSee('Learn how the web works.')
            ->assertSee('Describe HTTP and HTML.')
            ->assertSee('Module One')
            ->assertSee('How HTTP Works')
            ->assertSee('25')
            ->assertSee('Required')
            ->assertSee('Free')
            ->assertDontSee('SECRET LESSON CONTENT')
            ->assertDontSee('A short summary.')
            ->assertDontSee('SECRET MATERIAL TITLE')
            ->assertDontSee('SECRET MATERIAL CONTENT')
            ->assertDontSee('https://secret.example.com/file');
    }

    public function test_public_outline_hides_draft_content_inside_a_published_course(): void
    {
        $course = $this->makePublishedCourse();
        $publishedModule = Module::factory()->for($course, 'course')->create([
            'title' => 'Published Module',
            'position' => 2,
            'status' => ContentStatus::Published,
        ]);
        Lesson::factory()->for($publishedModule, 'module')->create([
            'title' => 'Published Lesson',
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);
        $draftModule = Module::factory()->for($course, 'course')->create([
            'title' => 'Draft Module',
            'position' => 3,
            'status' => ContentStatus::Draft,
        ]);
        Lesson::factory()->for($draftModule, 'module')->create([
            'title' => 'Draft Lesson',
            'position' => 1,
            'status' => ContentStatus::Draft,
        ]);

        $this->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Published Module')
            ->assertSee('Published Lesson')
            ->assertDontSee('Draft Module')
            ->assertDontSee('Draft Lesson');
    }

    public function test_public_pages_never_show_the_instructor_email(): void
    {
        $instructor = User::factory()->instructor()->create([
            'name' => 'Rhea Santos',
            'email' => 'rhea.santos@example.test',
        ]);
        $course = $this->makePublishedCourse([], $instructor);

        $this->get('/courses')->assertOk()->assertSee('Rhea Santos')->assertDontSee('rhea.santos@example.test');
        $this->get("/courses/{$course->slug}")->assertOk()->assertSee('Rhea Santos')->assertDontSee('rhea.santos@example.test');
    }

    public function test_course_title_is_html_escaped(): void
    {
        $course = $this->makePublishedCourse(['title' => '<script>alert(1)</script>Networking']);

        $response = $this->get("/courses/{$course->slug}")->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_catalog_search_filters_by_title(): void
    {
        $this->makePublishedCourse(['title' => 'Networking Basics']);
        $this->makePublishedCourse(['title' => 'Database Design']);

        $this->get('/courses?q=network')
            ->assertOk()
            ->assertSee('Networking Basics')
            ->assertDontSee('Database Design');
    }

    public function test_catalog_filters_by_category_level_and_type(): void
    {
        $this->makePublishedCourse([
            'title' => 'Networking Basics',
            'category' => 'Networking',
            'level' => CourseLevel::Beginner,
            'course_type' => CourseType::Free,
        ]);
        $this->makePublishedCourse([
            'title' => 'Advanced Networking',
            'category' => 'Networking',
            'level' => CourseLevel::Advanced,
            'course_type' => CourseType::Paid,
            'price_minor' => 25000,
        ]);
        $this->makePublishedCourse([
            'title' => 'Database Design',
            'category' => 'Databases',
            'level' => CourseLevel::Intermediate,
            'course_type' => CourseType::Free,
        ]);

        $this->get('/courses?category=Networking')
            ->assertOk()
            ->assertSee('Networking Basics')
            ->assertSee('Advanced Networking')
            ->assertDontSee('Database Design');

        $this->get('/courses?level=advanced')
            ->assertOk()
            ->assertSee('Advanced Networking')
            ->assertDontSee('Networking Basics');

        $this->get('/courses?course_type=paid')
            ->assertOk()
            ->assertSee('Advanced Networking')
            ->assertDontSee('Database Design');
    }

    public function test_catalog_combines_search_and_filters(): void
    {
        $this->makePublishedCourse([
            'title' => 'Networking Basics',
            'category' => 'Networking',
            'level' => CourseLevel::Beginner,
        ]);
        $this->makePublishedCourse([
            'title' => 'Networking Advanced Lab',
            'category' => 'Networking',
            'level' => CourseLevel::Advanced,
        ]);

        $this->get('/courses?q=advanced&category=Networking&level=advanced')
            ->assertOk()
            ->assertSee('Networking Advanced Lab')
            ->assertDontSee('Networking Basics');
    }

    public function test_unknown_filter_values_are_ignored(): void
    {
        $this->makePublishedCourse(['title' => 'Networking Basics']);

        $this->get('/courses?level=not-a-level&course_type=not-a-type&category=')
            ->assertOk()
            ->assertSee('Networking Basics');
    }

    public function test_search_text_with_sql_characters_is_handled_safely(): void
    {
        $this->makePublishedCourse(['title' => 'Networking Basics']);

        $this->get('/courses?q=%27%20OR%201%3D1--')
            ->assertOk()
            ->assertSee('No published courses');
    }

    public function test_catalog_shows_a_clear_empty_state(): void
    {
        $this->get('/courses')
            ->assertOk()
            ->assertSee('No published courses')
            ->assertSee('Clear filters');
    }

    public function test_catalog_paginates(): void
    {
        Course::factory()->count(13)->create(['status' => CourseStatus::Published]);

        $firstPage = $this->get('/courses')->assertOk();
        $firstPage->assertSee('page=2', false);

        $this->get('/courses?page=2')->assertOk();
    }

    public function test_catalog_offers_only_categories_that_have_published_courses(): void
    {
        $this->makePublishedCourse(['title' => 'Networking Basics', 'category' => 'Networking']);
        Course::factory()->create([
            'title' => 'Draft Category Course',
            'status' => CourseStatus::Draft,
            'category' => 'Secret Draft Category',
        ]);

        $response = $this->get('/courses')->assertOk();

        $response->assertSee('Networking');
        $response->assertDontSee('Secret Draft Category');
    }

    public function test_missing_course_slug_returns_not_found(): void
    {
        $this->get('/courses/does-not-exist')->assertNotFound();
    }

    public function test_home_page_and_header_link_to_the_catalog(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('courses.index'), false);
    }

    public function test_the_sign_in_page_offers_a_way_back_to_the_public_pages(): void
    {
        // The sign in page is deliberately narrow: one form and a way back. It
        // does not carry a catalog link, so a visitor who arrives there without
        // an account must still be able to reach the public pages in one click.
        $response = $this->get('/login')->assertOk();

        $response->assertSee(route('home'), false);

        $body = (string) $response->getContent();

        $this->assertStringContainsString(
            'href="'.route('home').'"',
            $body,
            'The sign in page must link back to the home page, where the catalog is reachable.'
        );
    }

    public function test_authenticated_student_can_also_browse_the_catalog(): void
    {
        $student = User::factory()->create();
        $this->makePublishedCourse(['title' => 'Networking Basics']);

        $this->actingAs($student)
            ->get('/courses')
            ->assertOk()
            ->assertSee('Networking Basics');
    }

    public function test_paid_course_shows_a_formatted_php_price(): void
    {
        $course = $this->makePublishedCourse([
            'title' => 'Paid Lab',
            'course_type' => CourseType::Paid,
            'price_minor' => 125050,
        ]);

        $body = $this->get("/courses/{$course->slug}")->assertOk()->getContent();

        // The product plan gives `₱499.00` as the display format for a peso
        // amount, so a public price is written as `₱1,250.50`.
        $this->assertStringContainsString('1,250.50', (string) $body);

        // The stored amount is integer minor units, so the raw value is never
        // printed and never appears as a float.
        $this->assertStringNotContainsString('125050', (string) $body);
        $this->assertStringNotContainsString('1250.5', (string) $body);
    }

    public function test_phase_five_f_adds_no_lesson_access_payment_or_download_routes(): void
    {
        $this->assertFalse(Route::has('student.enrollments.index'));
        $this->assertFalse(Route::has('student.progress.show'));
        $this->assertFalse(Route::has('instructor.courses.materials.upload'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePublishedCourse(array $attributes = [], ?User $instructor = null): Course
    {
        $instructor ??= User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create(array_merge([
            'status' => CourseStatus::Published,
            'published_at' => now()->subDay(),
        ], $attributes));

        if ($course->modules()->doesntExist()) {
            $module = Module::factory()->for($course, 'course')->create(['status' => ContentStatus::Published]);
            Lesson::factory()->for($module, 'module')->create(['status' => ContentStatus::Published]);
        }

        return $course;
    }
}
