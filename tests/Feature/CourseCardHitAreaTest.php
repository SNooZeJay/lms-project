<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The course card is clickable through a stretched link on its title.
 *
 * That pattern is only safe when the card is a positioning context. Without one,
 * the pseudo element that covers the card resolves against the page instead, and
 * the entire viewport silently becomes a link to one course. The symptom is a
 * pointer that looks clickable over empty page and a click that navigates
 * somewhere the visitor never aimed at, which is why it is pinned here.
 */
class CourseCardHitAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_card_is_a_positioning_context_for_the_stretched_title_link(): void
    {
        $course = Course::factory()->create([
            'title' => 'Programming Fundamentals with Python',
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 120000,
        ]);

        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('after:absolute', $body, 'The card should use a stretched link.');

        // The list item has to establish the containing block, otherwise the
        // stretched area covers the page instead of the card.
        $this->assertMatchesRegularExpression(
            '/<li class="card relative[^"]*"/',
            $body,
            'The course card must be a positioning context, or the stretched title link covers the whole page.'
        );
    }

    public function test_the_card_button_sits_above_the_stretched_link(): void
    {
        $course = Course::factory()->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        // Checked in the component rather than the page, because the page has
        // other positioned elements that have nothing to do with the card.
        $html = (string) view('components.course-card', ['course' => $course])->render();

        // Exactly one positioned element inside the card: the wrapper around the
        // button, which is what has to be raised above the stretched title link.
        //
        // This used to expect two, because the title row was positioned as well.
        // The reason given was that the button must not be swallowed, and the
        // button does not need the title row to achieve that. A positioned
        // element paints above a non-positioned one that comes later in the
        // document, and the button already comes later.
        //
        // Positioning the title row had a cost nobody had measured. The stretched
        // overlay resolves against the *nearest* positioned ancestor, so the
        // title row became it and the overlay stopped at the title and the price
        // badge. Measured at 390 pixels that was 316 by 26 of a 358 by 314 card,
        // leaving the description, the counts, the level and the instructor name
        // all activating nothing. Raising the button and covering the whole card
        // are separate concerns and only the first needs a positioning context.
        $this->assertSame(
            1,
            preg_match_all('/class="relative[^"]*"/', $html),
            'Only the button wrapper should be positioned inside the card. A second one between the '
            .'card and its title link truncates the stretched link to that element.'
        );
    }

    /**
     * Nothing between the card and its title link may be positioned.
     *
     * This is the general form of the fault above. A card is clickable through a
     * stretched link on its title, and where that overlay lands depends entirely
     * on the nearest positioned ancestor. Any `relative` introduced inside the
     * card later, for any reason, silently turns most of the card into dead
     * space, and nothing about the change looks like it could do that.
     */
    public function test_nothing_between_the_card_and_its_title_link_is_positioned(): void
    {
        $course = Course::factory()->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $html = (string) view('components.course-card', ['course' => $course])->render();

        $anchorAt = strpos($html, 'after:absolute');
        $this->assertNotFalse($anchorAt, 'The card has no stretched link, so this test proved nothing.');

        $cardStart = strrpos(substr($html, 0, (int) $anchorAt), '<li');
        $this->assertNotFalse($cardStart, 'The stretched link is not inside a list item.');

        $before = substr($html, (int) $cardStart, (int) $anchorAt - (int) $cardStart);

        $this->assertSame(
            1,
            preg_match_all('/\brelative\b/', $before),
            'Something between the card and its title link is positioned, so the stretched link '
            .'resolves against it and covers only that part of the card.'
        );
    }

    public function test_no_other_view_stretches_a_link_without_a_container(): void
    {
        // The catalog renders its own card and does not stretch the title, so it
        // cannot develop this fault. Any view that starts stretching a link has
        // to bring its own containing block with it.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        $offenders = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'after:absolute') && ! str_contains($contents, 'card relative')) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views stretch a link without a positioning container: '.implode(', ', $offenders)
        );
    }

    public function test_a_compact_card_has_no_button_to_raise(): void
    {
        $course = Course::factory()->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        // Rendered in compact form the card is only the title link, which is the
        // case used inside a panel rather than a list.
        $html = (string) view('components.course-card', ['course' => $course, 'compact' => true])->render();

        $this->assertStringContainsString('after:absolute', $html);
        $this->assertStringNotContainsString('View course', $html);
    }
}
