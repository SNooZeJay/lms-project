<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The ambient surface on the authentication panel.
 *
 * A drifting highlight and a single hairline arc. Both are decoration, so the
 * tests here are mostly about the things that make decoration safe rather than
 * about how it looks: that it cannot silently disappear, that it cannot eat a
 * click, and that it stops for a reader who asked for reduced motion.
 */
class AuthPanelAmbientMotionTest extends TestCase
{
    private function css(): string
    {
        $files = glob(public_path('build/assets/app-*.css')) ?: [];
        $source = implode("\n", array_map('file_get_contents', $files));

        $this->assertNotSame('', $source, 'The built stylesheet was not found. Run the asset build first.');

        return $source;
    }

    public function test_the_panel_paints_a_gradient_between_two_different_blues(): void
    {
        $body = (string) $this->get('/login')->assertOk()->getContent();
        $css = $this->css();

        $this->assertStringContainsString('linear-gradient', $css);
        $this->assertMatchesRegularExpression(
            '/linear-gradient\(\s*to bottom,\s*var\(--color-primary\)[^)]*var\(--lms-brand-deep\)/',
            $css,
            'The panel gradient should run from the primary at the top to the deep tone at the bottom.'
        );

        // The deep end has to differ from the primary, or the panel is flat.
        $this->assertMatchesRegularExpression('/--lms-brand-deep:\s*#(?!1d4ed8)[0-9a-f]{6}/i', $css);
        $this->assertNotSame('', $body);
    }

    public function test_the_deep_tone_is_a_plain_value_and_not_a_color_mix(): void
    {
        // This is a real fault, not a style preference. The build tool rewrites a
        // color-mix() behind an @supports query and falls back to the primary on
        // any browser without color-mix support, which silently turns the
        // gradient into a flat fill on exactly the browsers least likely to be
        // checked by hand.
        $css = $this->css();

        $this->assertDoesNotMatchRegularExpression(
            '/--lms-brand-deep:\s*color-mix/',
            $css,
            'A color-mix deep tone collapses to flat on browsers without color-mix support.'
        );
    }

    public function test_both_motion_layers_are_declared_and_both_keyframes_exist(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('@keyframes auth-aside-drift', $css);
        $this->assertStringContainsString('@keyframes auth-aside-arc', $css);
        $this->assertMatchesRegularExpression('/animation:[^;}]*auth-aside-drift/', $css);
        $this->assertMatchesRegularExpression('/animation:[^;}]*auth-aside-arc/', $css);
    }

    public function test_the_motion_is_slow_and_never_rotates_or_morphs(): void
    {
        // Long durations, because the panel is read for as long as someone takes
        // to fill in a form. No rotation keyframe at all, so the arc keeps its
        // orientation and reads as depth rather than as motion.
        $css = $this->css();

        $this->assertMatchesRegularExpression('/animation:6[0-9]s[^;}]*auth-aside-drift/', $css);
        $this->assertMatchesRegularExpression('/animation:[3-9][0-9]s[^;}]*auth-aside-arc/', $css);

        $keyframes = $this->keyframes();
        foreach (['auth-aside-drift', 'auth-aside-arc'] as $name) {
            $this->assertArrayHasKey($name, $keyframes, "Missing keyframes for {$name}.");
            $this->assertStringNotContainsStringIgnoringCase('rotate', $keyframes[$name]);
        }
    }

    public function test_the_arc_breathes_by_a_negligible_amount(): void
    {
        // A heartbeat, not a pulse. Anything past a couple of percent scale would
        // be visible movement, which is the thing being asked to avoid.
        $keyframes = $this->keyframes();

        $this->assertMatchesRegularExpression('/scale\(1\.0[0-2]\d?\)/', $keyframes['auth-aside-arc']);
    }

    public function test_reduced_motion_stops_both_layers_outright(): void
    {
        // The stylesheet's blanket reduced-motion rule leaves an infinite
        // animation cycling every ten thousandths of a second, which flickers
        // instead of resting. These two are switched off so the panel settles on
        // the first frame of each animation.
        $css = preg_replace('/\s+/', '', $this->css());

        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);
        $this->assertStringContainsString(
            '.auth-aside:before,.auth-aside:after{animation:none!important}',
            $css,
            'Reduced motion must stop the panel motion outright.'
        );
    }

    public function test_the_layers_cannot_intercept_a_click(): void
    {
        // The panel sits beside the form rather than over it, but a decorative
        // layer that swallows pointer events is the kind of defect that only
        // shows up on one screen.
        $css = $this->css();

        $this->assertSame(
            2,
            preg_match_all('/\.auth-aside:(before|after)\{[^}]*pointer-events:none/', $css),
            'Both decorative layers must be transparent to the pointer.'
        );
    }

    public function test_the_content_sits_above_the_decorative_layers(): void
    {
        $body = (string) $this->get('/login')->assertOk()->getContent();

        // The layers are positioned at zero and the panel content is raised, so
        // the drifting surface can never wash over the text.
        $this->assertSame(
            2,
            preg_match_all('/z-index:0/', $this->css()),
            'The decorative layers should be positioned at zero.'
        );
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($body, 'z-10'),
            'The panel content should be raised above the decorative layers.'
        );
    }

    /**
     * The body of each keyframes block, matched by counting braces.
     *
     * A regular expression cannot do this. The blocks are nested, so a lazy
     * pattern stops at the first closing brace and returns the first keyframe
     * instead of the whole rule, which is how a test ends up asserting against
     * half the thing it means to check.
     *
     * @return array<string, string>
     */
    private function keyframes(): array
    {
        $css = $this->css();
        $found = [];
        $offset = 0;
        $length = strlen($css);

        while (($start = strpos($css, '@keyframes', $offset)) !== false) {
            $open = strpos($css, '{', $start);

            if ($open === false) {
                break;
            }

            $name = trim(substr($css, $start + strlen('@keyframes'), $open - $start - strlen('@keyframes')));

            $depth = 0;
            $i = $open;

            for (; $i < $length; $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } elseif ($css[$i] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                }
            }

            $found[$name] = preg_replace('/\s+/', '', substr($css, $open + 1, $i - $open - 1));
            $offset = $i + 1;
        }

        return $found;
    }
}
