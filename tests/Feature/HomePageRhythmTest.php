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

        /*
         | The padding is the rhythm, and it now has a name.
         |
         | It used to be a literal, `py-12 sm:py-14 lg:py-16`, written on the
         | section component and checked here by string. That held while the
         | component was the only place it appeared. The About page then needed the
         | same rhythm, and repeating the literal in a second file is how the two
         | drift apart, which is the fault this test was written to prevent.
         |
         | So the step is `.band`, declared once in the stylesheet, and the rule is
         | now stronger: every band on every public page is that class, and there
         | is exactly one declaration of it. A band that reaches for its own
         | padding is a band the page has stopped being a page about.
         */
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertSame(
            1,
            preg_match_all('/^\s*\.band\s*\{/m', $css),
            'The band step must be declared exactly once, in the stylesheet.'
        );

        $this->assertStringContainsString(
            'band',
            (string) file_get_contents(resource_path('views/components/home/section.blade.php')),
            'The section component should use the shared .band step rather than its own padding.'
        );

        // And every rendered band is that class, so no section has quietly gained
        // its own.
        preg_match_all('/<section\b[^>]*class="([^"]*)"[^>]*aria-labelledby="[^"]+"/', $html, $matches);

        $this->assertGreaterThanOrEqual(4, count($matches[1]), 'Expected several labelled bands on the home page.');

        foreach ($matches[1] as $class) {
            if (str_contains($class, 'band-first')) {
                continue;
            }

            $this->assertStringContainsString(
                'band',
                $class,
                "A home page band does not use the shared step: {$class}"
            );

            $this->assertDoesNotMatchRegularExpression(
                '/\b[ps]{1,2}y-\d/',
                $class,
                "A band carries its own vertical padding rather than the shared step: {$class}. "
                .'The rhythm lives in .band and nowhere else.'
            );
        }
    }

    public function test_the_hero_does_not_add_extra_space_below_itself(): void
    {
        $this->publish('Free One', CourseType::Free);

        $html = $this->home();

        /*
         | The hero may open with more air than a band. It must not close with
         | more.
         |
         | A wide margin below the hero makes the gap between it and the first band
         | wider than every other gap on the page, and that is the fault this test
         | exists to catch. The hero has its own class now, `.band-first`, which is
         | the same idea as before with the value in one place: no hairline, because
         | a page does not open with a rule, and a tighter top, because the content
         | is already at the top of the document.
         |
         | The two closing values are compared against each other rather than
         | against numbers written here. A test that restates the value it is
         | checking has to be edited every time the value is, and the edit is the
         | moment the check stops being made. Reading both out of the stylesheet
         | means this can only fail if the two genuinely disagree.
         */
        $this->assertStringContainsString('band-first', $html, 'The hero does not use the opening class.');

        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertSame(
            1,
            preg_match('/\.band\s*\{[^}]*\}/', $css, $band),
            'The .band step was not found in the stylesheet.'
        );

        $this->assertSame(
            1,
            preg_match('/\.band-first\s*\{[^}]*\}/', $css, $first),
            'The .band-first opening step was not found in the stylesheet.'
        );

        $bandBottom = $this->bottomPadding($band[0]);
        $heroBottom = $this->bottomPadding($first[0]);

        $this->assertNotSame([], $bandBottom, 'The .band step declares no bottom padding.');
        $this->assertNotSame([], $heroBottom, 'The .band-first step declares no bottom padding.');

        $this->assertSame(
            $bandBottom,
            $heroBottom,
            'The hero closes with different space from a band, so the first gap on the page '
            .'is a different size from every other one. A band resolved to '
            .json_encode($bandBottom).' and the hero to '.json_encode($heroBottom).'.'
        );
    }

    /**
     * The bottom padding a rule actually produces, at every breakpoint.
     *
     * `py-*` sets both edges, so a rule written `py-10 sm:py-12 lg:py-14` closes
     * at 10, 12 and 14 without ever naming a bottom value. A `pb-*` in the same
     * rule overrides it for the edges it names, so this reads the cascade rather
     * than searching for a token.
     *
     * That resolution is the whole reason the comparison can be made: `.band` and
     * `.band-first` are written in different notations and produce the same
     * bottom spacing, which is exactly the case a string comparison gets wrong.
     *
     * @return array<string, string> breakpoint prefix => value
     */
    private function bottomPadding(string $rule): array
    {
        $padding = [];

        preg_match_all(
            '~(?:^|\s)((?:sm:|md:|lg:|xl:)?)(py|pb)-([^\s;]+)~',
            $rule,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as [, $breakpoint, $property, $value]) {
            $padding[$breakpoint] = $value;
        }

        ksort($padding);

        return $padding;
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
        /*
         | The wording changed and the requirement did not.
         |
         | "Teaching here" and "How it works" named a topic rather than saying
         | something. They are now "If you teach here" and "From a course to a
         | certificate", which say the same thing and read as one page rather than
         | as two headings from two documents.
         |
         | What this is for is unchanged: a student is the visitor this page is
         | written for, and a page that never mentions teaching answers an
         | instructor by omission.
         */
        $this->assertStringContainsString('If you teach here', $html);
        $this->assertStringContainsString('From a course to a certificate', $html);

        // The instructor workflow, in the order it happens in. Each step names a
        // screen that exists rather than a promise, and the last one is the step
        // that used to be missing from the page entirely.
        foreach (['Write it', 'Build it', 'Set the questions', 'Publish it'] as $step) {
            $this->assertStringContainsString($step, $html);
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
