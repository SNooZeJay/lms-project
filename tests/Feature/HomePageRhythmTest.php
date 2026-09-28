<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page holds its shape.
 *
 * The page was four near-identical sections that had each been given their own
 * vertical padding, so the gaps between them did not match and nothing recorded
 * whether that was deliberate. They are one component now, and these tests are
 * what keeps it one component.
 *
 * What is checked here is the things that fail quietly. A section that loses its
 * hairline still renders. A heading that drifts off the container edge still
 * renders. A rhythm value that changes by four pixels is invisible in a
 * screenshot and obvious when two sections are stacked.
 */
class HomePageRhythmTest extends TestCase
{
    // These tests create published courses, so the database has to be reset
    // between them or the rows survive into the next test file.
    use RefreshDatabase;

    private function home(): string
    {
        return (string) $this->get(route('home'))->assertOk()->getContent();
    }

    private function publish(string $title, CourseType $type): Course
    {
        return Course::factory()->create([
            'title' => $title,
            'status' => CourseStatus::Published,
            'course_type' => $type,
            'price_minor' => $type === CourseType::Paid ? 120000 : 0,
        ]);
    }

    public function test_every_section_carries_the_same_vertical_padding(): void
    {
        $this->publish('Free One', CourseType::Free);
        $this->publish('Paid One', CourseType::Paid);

        $html = $this->home();

        // The padding is the rhythm. It is declared once on the section
        // component, so every band has to reference the same declaration rather
        // than repeating a value that can then drift.
        $this->assertSame(
            1,
            substr_count(
                (string) file_get_contents(resource_path('views/components/home/section.blade.php')),
                'py-12 sm:py-14 lg:py-16'
            ),
            'The section padding must be declared once, on the section component.'
        );

        // And the rendered sections must all be the same height of padding, so
        // no section has quietly gained its own.
        preg_match_all('/<section[^>]*class="([^"]*border-t[^"]*)"/', $html, $matches);

        $this->assertGreaterThanOrEqual(3, count($matches[1]), 'Expected several bordered sections on the home page.');

        foreach ($matches[1] as $class) {
            $this->assertStringContainsString(
                'py-12 sm:py-14 lg:py-16',
                $class,
                "A home page section does not use the shared padding: {$class}"
            );
        }
    }

    public function test_the_hero_does_not_add_extra_space_below_itself(): void
    {
        $this->publish('Free One', CourseType::Free);

        $html = $this->home();

        // The hero is the entry to the page, so it may open with more air than a
        // section does. It must not also close with more, because that makes the
        // gap between the hero and the first band wider than every other gap on
        // the page, and that is the fault this test exists to catch.
        $this->assertMatchesRegularExpression(
            '/<section[^>]*class="[^"]*\bpt-12\b[^"]*\bpb-12\b[^"]*"/',
            $html,
            'The hero must open and close with the same spacing below the small breakpoint.'
        );

        $this->assertMatchesRegularExpression(
            '/<section[^>]*class="[^"]*\blg:pt-20\b[^"]*\blg:pb-16\b[^"]*"/',
            $html,
            'The hero may open with more space than a section, but it must close with the same space a section closes with.'
        );
    }

    public function test_every_section_is_labelled_by_its_own_heading(): void
    {
        $this->publish('Free One', CourseType::Free);
        $this->publish('Paid One', CourseType::Paid);

        $html = $this->home();

        preg_match_all('/<section\b[^>]*aria-labelledby="([^"]+)"/', $html, $sections);

        $this->assertNotEmpty($sections[1], 'Every band on the page should name the heading that labels it.');

        foreach ($sections[1] as $id) {
            $this->assertStringContainsString(
                'id="'.$id.'"',
                $html,
                "A section points at a heading that does not exist: {$id}"
            );
        }
    }

    public function test_the_course_grids_use_one_column_count(): void
    {
        $this->publish('Free One', CourseType::Free);
        $this->publish('Free Two', CourseType::Free);
        $this->publish('Paid One', CourseType::Paid);
        $this->publish('Paid Two', CourseType::Paid);
        $this->publish('Paid Three', CourseType::Paid);

        // The free group holds two cards and the paid group holds three. If the
        // grid adapted its column count to the number of cards, the free cards
        // would be half again as wide as the paid ones and the description would
        // run to ninety odd characters a line.
        $html = $this->home();

        preg_match_all('/<ul[^>]*class="([^"]*grid gap-5[^"]*)"/', $html, $grids);

        $this->assertGreaterThanOrEqual(2, count($grids[1]), 'Expected a card grid per course group.');

        foreach ($grids[1] as $class) {
            $this->assertStringContainsString(
                'sm:grid-cols-2 lg:grid-cols-3',
                $class,
                "A course grid changed its column count: {$class}"
            );
        }
    }

    public function test_the_page_speaks_to_both_students_and_instructors(): void
    {
        $this->publish('Free One', CourseType::Free);

        $html = $this->home();

        // A student is the visitor this page was written for, but an instructor
        // opening the same link is deciding whether this is a place they can
        // teach. A page that never mentions teaching answers that by omission.
        $this->assertStringContainsString('Teaching here', $html);
        $this->assertStringContainsString('How it works', $html);

        // Each capability names a screen that exists, rather than a promise.
        foreach (['Write the course', 'Build the modules', 'Set the quizzes'] as $capability) {
            $this->assertStringContainsString($capability, $html);
        }
    }

    public function test_a_guest_is_sent_to_sign_in_rather_than_to_the_instructor_area(): void
    {
        $this->publish('Free One', CourseType::Free);

        $html = $this->home();

        // The instructor area sits behind authentication. A link to it from the
        // public home page would redirect a guest straight back to the sign in
        // page, which is a dead end dressed up as a call to action.
        $this->assertStringContainsString('Sign in to teach', $html);
        $this->assertStringNotContainsString(route('instructor.courses.create'), $html);
    }

    public function test_a_signed_in_instructor_is_sent_to_the_instructor_area(): void
    {
        $this->publish('Free One', CourseType::Free);

        $instructor = User::factory()->instructor()->create();

        $html = (string) $this->actingAs($instructor)->get(route('home'))->assertOk()->getContent();

        // The right destination for the person actually reading it.
        $this->assertStringContainsString(route('instructor.courses.create'), $html);
        $this->assertStringContainsString('Create a course', $html);
        $this->assertStringNotContainsString('Sign in to teach', $html);
    }

    public function test_the_numbered_steps_are_marked_up_as_an_ordered_list(): void
    {
        $this->publish('Free One', CourseType::Free);

        $html = $this->home();

        // The order is real information, so it belongs in the element rather
        // than in a number typed into a circle.
        $this->assertStringContainsString('<ol', $html);

        // And the circle is decorative, so a screen reader is not told the
        // number twice.
        $this->assertMatchesRegularExpression(
            '/<span[^>]*aria-hidden="true"[^>]*>\s*\d+\s*<\/span>/',
            $html,
            'The step number should be hidden from a screen reader, because the list already carries the order.'
        );
    }

    public function test_the_empty_catalog_state_keeps_its_heading(): void
    {
        // No courses at all. The page still has to be a set of labelled bands
        // rather than collapsing into one unlabelled message.
        $html = $this->home();

        $this->assertStringContainsString('The catalog is empty', $html);
        $this->assertStringContainsString('id="empty-catalog-heading"', $html);
        $this->assertStringContainsString('aria-labelledby="empty-catalog-heading"', $html);
    }
}
