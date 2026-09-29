<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The scroll animation is AOS, and the way it is wired has three requirements.
 *
 * The library does the part that is fiddly: it finds every element marked
 * `data-aos`, works out where each sits relative to the viewport, and adds
 * `aos-animate` when the reader arrives. Only four effects are declared in the
 * stylesheet because only four are used; the library ships thirty.
 *
 * Why this is a test and not a look at the page
 * ---------------------------------------------
 *
 * The machine this was written on reports `prefers-reduced-motion: reduce`, so
 * every browser pass over the public pages exercises the *disabled* path. That is
 * the right path to have verified by eye, and it is the wrong path to conclude
 * from. The animated path was never observed, only asserted, and an assertion
 * that has never been checked against a real animation is a comment.
 *
 * So the contract is pinned here instead, in the three places it can be wrong.
 *
 * The one that matters most
 * -------------------------
 *
 * AOS's own stylesheet hides anything carrying `data-aos`, unconditionally. If
 * that stylesheet arrives and the script does not, every marked element stays at
 * zero opacity for ever: nothing throws, nothing logs, and the page has no text
 * on it. That is why the hidden state here is gated on the `aos-init` class AOS
 * itself adds to an element while initialising. An element is only ever hidden by
 * code that has already proved it is there to reveal it.
 */
class ScrollAnimationTest extends TestCase
{
    private function bundle(): string
    {
        $scripts = glob(public_path('build/assets/app-*.js')) ?: [];

        $this->assertNotSame([], $scripts, 'The bundled script was not found. Run the asset build first.');

        return implode("\n", array_map('file_get_contents', $scripts));
    }

    private function stylesheet(): string
    {
        $files = glob(public_path('build/assets/app-*.css')) ?: [];

        $this->assertNotSame([], $files, 'The built stylesheet was not found. Run the asset build first.');

        return implode("\n", array_map('file_get_contents', $files));
    }

    /**
     * A pattern matching the rule for one AOS effect, quotes or no quotes.
     *
     * The minifier rewrites `[data-aos='fade-up']` to `[data-aos=fade-up]`, and an
     * assertion written against the source quoting therefore fails against a
     * correctly built stylesheet. Which is a test of the minifier.
     */
    private function selectorFor(string $effect): string
    {
        return '/\[data-aos=[\'"]?'.preg_quote($effect, '/').'[\'"]?\]/';
    }

    public function test_the_library_is_installed_and_bundled(): void
    {
        $this->assertFileExists(
            base_path('node_modules/aos/package.json'),
            'AOS is not installed, so the scroll animation cannot run at all.'
        );

        $this->assertStringContainsString(
            'aos-init',
            $this->bundle(),
            'The bundle carries no AOS. Either the import was dropped or the build is stale.'
        );
    }

    /**
     * The options, each for a reason stated where it is chosen.
     */
    public function test_it_is_configured_for_one_shot_and_for_a_settled_arrival(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertMatchesRegularExpression(
            '/AOS\.init\(\s*\{(.*?)\}\s*\)/s',
            $source,
            'AOS is not initialised with options, so it is running on its defaults.'
        );

        preg_match('/AOS\.init\(\s*\{(.*?)\}\s*\)/s', $source, $matches);
        $options = $matches[1];

        // Once. A band that re-animates every time it crosses the viewport is a
        // band that cannot be scrolled past quickly.
        $this->assertStringContainsString('once: true', $options);

        // Off under a reduced motion preference, through the library's own switch,
        // which strips the attributes rather than running the animation quickly.
        $this->assertStringContainsString('disable: reducedMotion.matches', $options);

        // A step, a duration and a curve. All three are visual decisions, and the
        // library's defaults are a linear ramp over four hundred milliseconds,
        // which reads as mechanical on a page this size.
        $this->assertStringContainsString('offset:', $options);
        $this->assertStringContainsString('duration:', $options);
        $this->assertStringContainsString('easing:', $options);
    }

    /**
     * The part that decides whether a page with no script is blank or readable.
     */
    public function test_an_element_is_never_hidden_unless_the_library_has_already_started(): void
    {
        $css = $this->stylesheet();

        // The hidden state must be scoped to .aos-init. An unscoped
        // `[data-aos] { opacity: 0 }` is what makes a missing script a blank page.
        $this->assertMatchesRegularExpression(
            '/\[data-aos\]\.aos-init\s*\{[^}]*opacity:\s*0/',
            $css,
            'The hidden state is not gated on .aos-init. If the script never runs, every marked '
            .'element stays invisible and the page has no text on it.'
        );

        // And it must not be hidden unconditionally anywhere else either.
        $this->assertDoesNotMatchRegularExpression(
            '/\[data-aos\]\s*\{[^}]*opacity:\s*0/',
            $css,
            'There is an ungated [data-aos] rule setting opacity to zero.'
        );

        // The library is never imported wholesale, only its behaviour.
        $this->assertStringNotContainsString(
            'aos/dist/aos.css',
            $this->bundle(),
            'The library stylesheet was imported. It hides every [data-aos] element on its own, which '
            .'is exactly the unconditional hiding this design is avoiding.'
        );
    }

    /**
     * The four effects the site actually asks for, and the arrival.
     */
    public function test_the_declared_effects_match_the_ones_the_views_use(): void
    {
        $css = $this->stylesheet();

        foreach (['fade-up', 'fade-down', 'fade-left', 'fade-right'] as $effect) {
            $this->assertMatchesRegularExpression(
                $this->selectorFor($effect),
                $css,
                "The effect `{$effect}` is declared in the comments but has no rule."
            );
        }

        // And every effect a view actually asks for has a rule.
        $used = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                '/data-aos="([a-z-]+)"/',
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $effect) {
                $used[$effect] = true;
            }
        }

        $this->assertNotSame([], $used, 'No view asks for an animation, so this test proved nothing.');

        foreach (array_keys($used) as $effect) {
            $this->assertMatchesRegularExpression(
                $this->selectorFor($effect),
                $css,
                "A view asks for the effect `{$effect}` and there is no rule for it, so it would "
                .'fade in with no movement, or not at all.'
            );
        }
    }

    /**
     * The reduced motion backstop, in the stylesheet as well as in script.
     */
    public function test_a_reduced_motion_preference_still_ends_with_the_content_visible(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{.*?\[data-aos\]\.aos-init\s*\{[^}]*opacity:\s*1/s',
            $css,
            'Under a reduced motion preference the animation is not switched off, so an element that '
            .'has been marked .aos-init stays at zero opacity.'
        );
    }

    /**
     * Motion must not move a box.
     *
     * Every rule in the motion block changes opacity or transform. A rule that
     * changed width, height, top or margin would reflow the page as it animates,
     * which is the failure mode that makes scroll animation feel cheap.
     */
    public function test_no_animation_changes_a_box(): void
    {
        $css = $this->stylesheet();

        // The layout properties a scroll animation must never touch.
        foreach (['width:', 'height:', 'top:', 'left:', 'margin', 'padding'] as $property) {
            $this->assertDoesNotMatchRegularExpression(
                '/\[data-aos[^\{]*\.[a-z-]+\s*\{[^}]*'.preg_quote($property, '/').'/',
                $css,
                "An AOS rule changes `{$property}`, so the page reflows while it animates."
            );
        }
    }
}
