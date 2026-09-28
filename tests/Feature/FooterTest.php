<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer is the same footer everywhere.
 *
 * There used to be two of them, written out separately in two layouts, and they
 * had already drifted: the public one had grown four columns while the
 * workspace one stayed two lines of text, and neither matched the section
 * heading style the navigation already used. They are now one component, and
 * these tests are what stops them drifting apart again.
 *
 * The failure these guard against is quiet. A footer does not throw when a
 * column collapses or a link points at a page that does not exist. It just
 * looks like it was left that way.
 */
class FooterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The site footer, identified by the class its links carry.
     *
     * A plain <footer> selector is not enough: the certificate page has a
     * <footer> that is the metadata block of a printable document, which is
     * correct semantic HTML and nothing to do with the site footer.
     */
    private function siteFooter(string $html): ?string
    {
        if (! str_contains($html, 'footer-link')) {
            return null;
        }

        // The footer is the last one in the document, which is where a landmark
        // footer belongs, and the one containing the links.
        preg_match_all('/<footer\b.*?<\/footer>/s', $html, $matches);

        foreach ($matches[0] as $candidate) {
            if (str_contains($candidate, 'footer-link')) {
                return $candidate;
            }
        }

        return null;
    }

    public function test_the_public_footer_is_present_and_carries_its_links(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $footer = $this->siteFooter($html);

        $this->assertNotNull($footer, 'The public pages must have a site footer.');

        // The two pages the sign in consent line links to, and the catalog.
        // A page that is linked from somewhere has to exist.
        $this->assertStringContainsString(route('courses.index'), $footer);
        $this->assertStringContainsString(route('legal.terms'), $footer);
        $this->assertStringContainsString(route('legal.privacy'), $footer);
        $this->assertStringContainsString(route('login'), $footer);
    }

    public function test_the_workspace_footer_is_present_and_offers_the_legal_terms(): void
    {
        $user = User::factory()->create();

        $html = (string) $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->getContent();

        $footer = $this->siteFooter($html);

        $this->assertNotNull($footer, 'The signed in workspace must have a site footer.');

        $this->assertStringContainsString(route('legal.terms'), $footer);
        $this->assertStringContainsString(route('legal.privacy'), $footer);

        // The brand, so the footer identifies the product rather than being a
        // row of links with no owner.
        $this->assertStringContainsString('data-brand-mark', $footer);
    }

    public function test_both_footers_are_the_same_component(): void
    {
        $user = User::factory()->create();

        $public = $this->siteFooter((string) $this->get(route('home'))->assertOk()->getContent());
        $workspace = $this->siteFooter((string) $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->getContent());

        $this->assertNotNull($public);
        $this->assertNotNull($workspace);

        // Both carry the shared surface, so a change to the footer's colour or
        // its top border lands on every page at once.
        foreach (['border-t border-line bg-surface', 'max-w-7xl'] as $marker) {
            $this->assertStringContainsString($marker, $public, "The public footer is missing {$marker}.");
            $this->assertStringContainsString($marker, $workspace, "The workspace footer is missing {$marker}.");
        }

        // The copyright band, which used to exist on one footer only.
        foreach ([$public, $workspace] as $footer) {
            $this->assertStringContainsString('&copy;', $footer);
            $this->assertStringContainsString((string) now()->year, $footer);
        }
    }

    public function test_the_workspace_footer_steps_aside_on_paper(): void
    {
        $user = User::factory()->create();

        $html = (string) $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<footer[^>]*data-print="hide"/',
            $html,
            'The workspace footer must be hidden when printing, because a certificate and a receipt are the only pages meant on paper.'
        );
    }

    public function test_every_footer_link_points_at_a_real_route(): void
    {
        $guest = (string) $this->get(route('home'))->assertOk()->getContent();
        $student = User::factory()->create();
        $member = (string) $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->getContent();

        foreach (['guest' => $guest, 'member' => $member] as $who => $html) {
            $footer = $this->siteFooter($html);
            $this->assertNotNull($footer, "The {$who} footer is missing.");

            preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/', $footer, $matches);

            $this->assertNotEmpty($matches[1], "The {$who} footer has no links at all.");

            foreach (array_unique($matches[1]) as $href) {
                // A dead link in a footer is the most visible way to look
                // unfinished, because every page has one.
                $this->assertTrue(
                    $this->resolves($href),
                    "The {$who} footer links to an address that is not a page: {$href}"
                );
            }
        }
    }

    public function test_the_footer_does_not_offer_registration_to_a_guest(): void
    {
        // Registration is reached from the sign in page. A guest who cannot sign
        // in yet should not be pushed to register from every page, and the home
        // page test pins the same rule, so the footer has to agree with it.
        $footer = $this->siteFooter((string) $this->get(route('home'))->assertOk()->getContent());

        $this->assertNotNull($footer);
        $this->assertStringNotContainsString(route('register'), $footer);
    }

    public function test_the_footer_offers_the_right_account_links_for_the_reader(): void
    {
        $guest = $this->siteFooter((string) $this->get(route('home'))->assertOk()->getContent());
        $student = User::factory()->create();
        $member = $this->siteFooter((string) $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->getContent());

        $this->assertNotNull($guest);
        $this->assertNotNull($member);

        // A guest is offered a way in. That link is the only difference the
        // account column makes between the two, and it is the one that matters:
        // a visitor who cannot get past the sign in page has to be able to
        // reach it from every page.
        $this->assertStringContainsString(route('login'), $guest);

        // A signed in reader is not sent back to the sign in page they already
        // passed, from either footer.
        $this->assertStringNotContainsString(route('login'), $member);

        // The workspace footer carries the legal terms and nothing else. The
        // account pages are in the sidebar already, and repeating them here
        // would make the footer a second, worse navigation. So the absence of
        // account links is the design, and it is pinned here on purpose.
        $this->assertStringNotContainsString(route('account.profile'), $member);
        $this->assertStringNotContainsString(route('account.password'), $member);
    }

    public function test_footer_links_carry_the_shared_focus_ring(): void
    {
        $footer = $this->siteFooter((string) $this->get(route('home'))->assertOk()->getContent());

        $this->assertNotNull($footer);

        // Every link uses the one class, rather than stacking a colour utility
        // on top of it. Which of two colour utilities wins is decided by their
        // order in the stylesheet, which a reader of the view cannot see.
        preg_match_all('/<a\b[^>]*>/', $footer, $matches);

        foreach ($matches[0] as $anchor) {
            $this->assertStringContainsString(
                'footer-link',
                $anchor,
                "A footer link does not use the shared class: {$anchor}"
            );
            $this->assertStringNotContainsString(
                'link-quiet',
                $anchor,
                "A footer link stacks link-quiet under another colour: {$anchor}"
            );
        }
    }

    public function test_the_footer_does_not_invent_social_links(): void
    {
        $footer = $this->siteFooter((string) $this->get(route('home'))->assertOk()->getContent());

        $this->assertNotNull($footer);

        // There is no social account for this product, so a link to one would be
        // a dead button. The public reference design had them and they were left
        // behind on purpose.
        foreach (['facebook', 'twitter', 'linkedin', 'instagram', 'github', 'youtube'] as $network) {
            $this->assertStringNotContainsStringIgnoringCase($network, $footer);
        }
    }

    /** Does this absolute or root relative address resolve to a real page? */
    private function resolves(string $href): bool
    {
        $path = (string) parse_url($href, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return true;
        }

        foreach (app('router')->getRoutes() as $route) {
            if ($route->uri() === trim($path, '/')) {
                return true;
            }
        }

        // A course page is a real pattern rather than a fixed list, because the
        // catalog grows without the footer changing.
        return preg_match('#^/courses/[A-Za-z0-9-]+$#', trim($path, '/')) === 1;
    }
}
