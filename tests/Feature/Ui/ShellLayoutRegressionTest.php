<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Three layout faults, each of which was invisible in the source and obvious on
 * a screen.
 *
 * A feature test can read the markup but not the rendered box, and every one of
 * these three bugs was correct as far as the markup was concerned. The class
 * names were plausible, the elements existed, the routes resolved. What was
 * wrong was only how a browser laid them out at a particular width, which is why
 * each of these was found by measuring a rendered page rather than by reading
 * the view.
 *
 * These tests therefore pin the mechanism, not the appearance. They cannot see a
 * regression in the cascade, but they will fail loudly if the specific mistake
 * that caused each fault is reintroduced.
 *
 * The rendered measurements live in the probes:
 *
 *   check-nav-labels.mjs  label visibility at 390, 1024 and 1440
 *   check-sticky.mjs      sidebar position after a 1200px scroll
 *   shoot-footer.mjs      footer height, sidebar pinning, overflow
 */
class ShellLayoutRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceHtml(): string
    {
        $user = User::factory()->create();

        return (string) $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();
    }

    /**
     * A layout with its Blade comments removed.
     *
     * These faults are documented in the layouts themselves, and the
     * documentation necessarily names the class that caused them. Asserting
     * against the raw file would therefore fail on the explanation of the fix,
     * which is the opposite of useful: the comment has to be able to say what
     * not to do. So the comments come out first and only real markup is
     * inspected.
     */
    private function layoutMarkup(string $layout): string
    {
        $source = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

        $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        $stripped = preg_replace('#/\*.*?\*/#s', '', (string) $stripped);

        return (string) $stripped;
    }

    /**
     * Every layout that renders a page, and therefore every one that has to keep
     * the sticky sidebar working.
     *
     * @return list<array{0: string}>
     */
    public static function layouts(): array
    {
        return [['app-shell'], ['app'], ['auth']];
    }

    /* ------------------------------------------------- the sidebar must stick */

    /**
     * The sidebar is position: sticky, and it did not stick.
     *
     * The cause was not the sticky declaration. It was `overflow-x-hidden` on
     * the html and the body. A non-visible overflow on one axis makes that
     * element a scroll container and the other axis computes to auto, so the
     * document became the sidebar's scroll container. The sidebar then anchored
     * to an element that never scrolls and rode away with the content, measured
     * at 1095px of movement on a 1200px scroll.
     *
     * `overflow-x-clip` gives the same protection against sideways overflow and
     * does not create a scroll container, so the sidebar holds its position.
     *
     * docs/design.md already called overflow-x-hidden "a safety net, never a
     * fix". It had quietly become the fault.
     */
    #[DataProvider('layouts')]
    public function test_no_layout_uses_overflow_hidden_where_it_would_break_sticky(string $layout): void
    {
        $this->assertStringNotContainsString(
            'overflow-x-hidden',
            $this->layoutMarkup($layout),
            "layouts/{$layout}.blade.php uses overflow-x-hidden, which turns the document into a scroll container and stops position:sticky from working."
        );
    }

    #[DataProvider('layouts')]
    public function test_no_layout_uses_overflow_hidden_on_its_html_or_body(string $layout): void
    {
        // The rendered page is the real evidence, so check what the server sent
        // rather than only what the file says. A layout could add the class from
        // a partial and the file would not show it.
        $rendered = $layout === 'auth'
            ? (string) $this->get(route('login'))->assertOk()->getContent()
            : $this->workspaceHtml();

        foreach (['<html', '<body'] as $tag) {
            $this->assertDoesNotMatchRegularExpression(
                '#<'.$tag.'[^>]*\boverflow-x-hidden\b#',
                $rendered,
                "The rendered <{$tag}> carries overflow-x-hidden on the {$layout} layout."
            );
        }
    }

    #[DataProvider('layouts')]
    public function test_every_layout_clips_sideways_overflow_instead(string $layout): void
    {
        $markup = $this->layoutMarkup($layout);

        // Both the root and the body, because one without the other still lets
        // a wide child push the page sideways.
        $this->assertSame(
            2,
            preg_match_all('#\boverflow-x-clip\b#', $markup),
            "layouts/{$layout}.blade.php must clip sideways overflow on both the html and the body."
        );
    }

    public function test_the_workspace_sidebar_is_declared_sticky_and_full_height(): void
    {
        $html = $this->workspaceHtml();

        $this->assertMatchesRegularExpression(
            '/<aside[^>]*data-workspace-sidebar[^>]*\blg:sticky\b/',
            $html,
            'The sidebar must be sticky from lg up, or it scrolls away with the content.'
        );

        $this->assertMatchesRegularExpression(
            '/<aside[^>]*data-workspace-sidebar[^>]*\blg:h-screen\b/',
            $html,
            'The sidebar must be a full viewport height, or its background stops part way down a long page.'
        );
    }

    /* --------------------------------------------- the drawer must show labels */

    /**
     * The mobile drawer rendered every navigation item as a bare icon.
     *
     * The label span was written `hidden xl:inline`, which is right for the
     * 80px icon rail and wrong for the drawer, because the drawer only exists
     * below lg and so never reaches xl. Every item lost its name for sighted
     * users while keeping it for screen readers, which is why nothing threw and
     * no test failed.
     *
     * The label is now one element, `lg:sr-only xl:not-sr-only`, so it paints
     * below lg, becomes screen-reader-only in the rail, and paints again at xl.
     * Two elements were tried first and were wrong twice: a second copy of the
     * words is announced twice in the drawer, and hiding that copy with
     * `hidden` removes it from the accessibility tree too, leaving the rail's
     * links with nothing but a title attribute.
     */
    public function test_the_navigation_label_is_one_element_that_toggles_with_the_breakpoint(): void
    {
        $view = (string) file_get_contents(resource_path('views/components/app/nav.blade.php'));

        $this->assertStringContainsString(
            'lg:sr-only xl:not-sr-only',
            $view,
            'The navigation label must be sr-only in the icon rail and painted everywhere else, from a single element.'
        );

        // The original fault, pinned so it cannot come back.
        $this->assertDoesNotMatchRegularExpression(
            '/class="hidden xl:inline"/',
            $view,
            'A label of "hidden xl:inline" is invisible in the mobile drawer, because the drawer never reaches xl.'
        );

        // One span, so a screen reader is not told the same thing twice. The
        // label legitimately appears twice in the file: once as the title
        // attribute and once inside the single span. What must not come back is
        // a second span.
        $this->assertSame(
            1,
            preg_match_all('/<span\b[^>]*>\s*\{\{\s*\$item\[.label.\]\s*\}\}\s*<\/span>/', $view),
            'The navigation label must be rendered in one span, not duplicated for the drawer and the sidebar.'
        );

        $this->assertSame(
            1,
            substr_count($view, 'title="{{ $item[\'label\'] }}"'),
            'Every navigation link needs exactly one title attribute for the icon rail.'
        );
    }

    public function test_every_navigation_item_is_named_when_the_label_is_not_painted(): void
    {
        $html = $this->workspaceHtml();

        // The rail hides the label on purpose, so the name has to survive
        // somewhere. A title attribute covers a pointer; the text itself is
        // still in the accessibility tree because the label is sr-only there
        // rather than hidden.
        // Only the workspace navigation. The footer also has a nav, and its
        // links are footer links, which are named by their text and have no
        // business carrying a title.
        preg_match_all('/<nav\b[^>]*aria-label="Workspace".*?<\/nav>/s', $html, $navs);

        $links = [];

        foreach ($navs[0] as $nav) {
            preg_match_all('/<a\b[^>]*>/', $nav, $anchors);

            foreach ($anchors[0] as $anchor) {
                $links[] = $anchor;
            }
        }

        $this->assertNotEmpty($links, 'No workspace navigation links were found in the shell.');

        foreach ($links as $anchor) {
            $this->assertStringContainsString(
                'title=',
                $anchor,
                "A navigation link has no title, so the icon rail would leave it unnamed: {$anchor}"
            );
        }
    }

    public function test_the_navigation_group_label_and_the_item_label_agree_about_the_drawer(): void
    {
        $view = (string) file_get_contents(resource_path('views/components/app/nav.blade.php'));

        // The group labels were already right, which is why the screenshot showed
        // OPERATIONS and ACCOUNT but no item names. Whatever the group label
        // does below lg, the item label has to do as well.
        $this->assertStringContainsString(
            'lg:hidden xl:inline',
            $view,
            'The group label shows in the drawer and at xl. The item label must behave the same way.'
        );
    }

    /* ---------------------------------------------------------- the footer band */

    /**
     * The workspace footer was a wide empty strip with a line drawn across it.
     *
     * Two rows split by a hairline held three small things, and the bottom right
     * carried the string "Information Technology learning platform", which says
     * nothing and is the filler a footer should never contain. The brand, the
     * legal links and the copyright now share one baseline.
     */
    public function test_no_footer_carries_a_filler_description(): void
    {
        $view = (string) file_get_contents(resource_path('views/components/footer.blade.php'));

        $this->assertStringNotContainsString(
            'Information Technology learning platform',
            $view,
            'The footer carried a content-free descriptor in the corner. It described nothing and earned its space by filling a gap.'
        );
    }

    public function test_the_workspace_footer_is_one_band_not_two(): void
    {
        $user = User::factory()->create();

        $html = (string) $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->getContent();

        preg_match_all('/<footer\b.*?<\/footer>/s', $html, $matches);

        $footer = null;

        foreach ($matches[0] as $candidate) {
            if (str_contains($candidate, 'footer-link')) {
                $footer = $candidate;
            }
        }

        $this->assertNotNull($footer);

        // The internal hairline that split the old two row band is gone. The
        // footer's own top border is not, so the assertion is about a border
        // inside the content, which is what used to cut it in half.
        $this->assertSame(
            1,
            substr_count($footer, 'border-t'),
            'The workspace footer has an internal divider again, which is what made it read as two empty strips.'
        );
    }

    public function test_the_workspace_footer_keeps_the_brand_the_terms_and_the_copyright(): void
    {
        $user = User::factory()->create();

        $html = (string) $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->getContent();

        preg_match_all('/<footer\b.*?<\/footer>/s', $html, $matches);

        foreach ($matches[0] as $footer) {
            if (! str_contains($footer, 'footer-link')) {
                continue;
            }

            // All three survive the redesign, and each earns its place: the
            // product identifies itself, the terms are reachable from every
            // page, and the copyright is the attribution.
            $this->assertStringContainsString('data-brand-mark', $footer);
            $this->assertStringContainsString('&copy;', $footer);
            $this->assertStringContainsString((string) now()->year, $footer);
            $this->assertStringContainsString(route('legal.terms'), $footer);
        }
    }
}
