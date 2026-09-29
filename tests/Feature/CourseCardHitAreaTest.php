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

        // Asked as a property rather than as a phrase.
        //
        // This used to assert the rendered markup contains the literal text
        // `card relative`. Adding a `lift` class to the card, which moves it two
        // pixels under a pointer, put `lift` between the two words and the
        // assertion failed while the card was still very much a positioning
        // context. The same lesson the walk at the bottom of this file already
        // records about counting the word `relative`: a string is a proxy for a
        // structural fact, and it stops standing for that fact the first time an
        // unrelated class is added to the element.
        //
        // So the question is asked of the parsed document: which positioned
        // elements is the title link inside? The card, and only the card.
        $this->assertSame(
            ['card'],
            $this->positionedAncestorsOfTheTitle($body),
            'The course card must be the positioning context for the stretched title link, '
            .'or the overlay resolves against the page and covers the whole viewport.'
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
        /*
         | The card, and nothing else.
         |
         | The button is raised above the overlay by a positioned wrapper, but that
         | wrapper is a *sibling* of the title rather than an ancestor of it, so it
         | does not appear here and cannot truncate anything. The card is the only
         | positioned element the title link is inside, which is the property that
         | matters: the overlay covers the card.
         |
         | The expectation was written as two elements before the walk was run, on the
         | assumption that the button wrapper was an ancestor. It is not, and the
         | walk said so.
         */
        $this->assertSame(
            ['card'],
            $this->positionedAncestorsOfTheTitle($html),
            'Something wrapping the title is positioned as well as the card, so the stretched link '
            .'resolves against it and covers only that part of the card.'
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

        $this->assertNotEmpty(
            $this->positionedAncestorsOfTheTitle($html),
            'The card has no stretched link, so this test proved nothing.'
        );

        /*
         | Read as a document rather than as a count of the word relative.
         |
         | The first version took the markup between the list item and the title link
         | and counted the occurrences of `relative` in it, expecting one. That
         | measures the wrong thing: what matters is which positioned elements are
         | *ancestors* of the title, because the stretched overlay resolves against
         | the nearest one. A sibling that happens to be positioned cannot truncate
         | anything, and adding the cover image added exactly that, so the count went
         | to two and the test reported a fault that was not there.
         |
         | Counting the word was a proxy for a structural property, and a proxy is
         | only useful while the thing it stands for is hard to check. The DOM can be
         | walked, so the property is checked directly.
         */
        $this->assertSame(
            ['card'],
            $this->positionedAncestorsOfTheTitle($html),
            'Something wrapping the title is positioned as well as the card, so the stretched link '
            .'resolves against it and covers only that part of the card.'
        );
    }

    public function test_no_other_view_stretches_a_link_without_a_container(): void
    {
        // A sweep, because a stretched link is a pattern somebody will reach for
        // again in a view that does not exist yet, and a pattern used without its
        // containing block turns the whole viewport into a link. The catalog
        // renders its own card and does not stretch the title, so it cannot
        // develop this fault.
        //
        // This compares class *tokens* rather than the phrase `card relative`. The
        // phrase version broke the moment a `lift` class was added between the two
        // words, and reported the course card as a fault when the card was fine.
        // The token version asks the question the test is actually about, which is
        // whether a view that stretches a link also declares a positioning context,
        // and it no longer depends on the order of the words.
        //
        // It is still a proxy, and it is worth saying so: a string cannot tell a
        // container from a sibling, which is precisely why the tests above walk
        // the parsed document instead. This sweep exists for the views with no
        // data to render, where walking the DOM is not available. When it reports
        // a file, the structural tests for that file are what settle it.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        // The four words Tailwind uses to make an element a positioning context.
        $positioning = '/(?:^|[\s"\'])(?:relative|absolute|fixed|sticky)(?:[\s"\']|$)/';

        $offenders = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'after:absolute') && preg_match($positioning, $contents) !== 1) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views stretch a link without declaring a positioning context: '.implode(', ', $offenders)
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

    /**
     * The positioned elements the title link is inside, nearest first.
     *
     * This is the property the whole file is about, asked directly: a stretched
     * overlay resolves against the nearest positioned ancestor, so a list of the
     * ancestors that are positioned is exactly the list of elements that can
     * truncate it. Counting the word `relative` in the markup was a proxy for that,
     * and the cover image proved the proxy wrong without the property being wrong.
     *
     * The DOM is parsed rather than the string searched, because a substring search
     * cannot tell a wrapper from a sibling and the difference is the whole question.
     *
     * @return array<int, string>
     */
    private function positionedAncestorsOfTheTitle(string $html): array
    {
        $document = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);

        $anchor = $xpath->query('//a[contains(@class, "after:absolute")]')->item(0);

        if (! $anchor instanceof \DOMElement) {
            return [];
        }

        $positioned = [];

        for ($node = $anchor->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
            $class = $node->getAttribute('class');

            // The same words Tailwind would act on, and the inline style, because a
            // component can position itself either way.
            if (preg_match('/\b(relative|absolute|fixed|sticky)\b/', $class) === 1
                || str_contains($class, 'after:absolute')
                || str_contains($node->getAttribute('style'), 'position')) {
                $positioned[] = $this->describe($node, $class);
            }
        }

        return $positioned;
    }

    /**
     * A name for an element, so a failure says which one is at fault.
     */
    private function describe(\DOMElement $node, string $class): string
    {
        if (str_contains($class, 'card')) {
            return 'card';
        }

        return trim($node->tagName).($node->getAttribute('id') !== '' ? '#'.$node->getAttribute('id') : '');
    }
}
