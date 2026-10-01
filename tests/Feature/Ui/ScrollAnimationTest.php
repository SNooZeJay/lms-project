<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The motion system, and the requirements it has to keep satisfying.
 *
 * These tests were originally about Animate on Scroll and were rewritten when
 * the library was removed. Most of what they asserted is still worth asserting,
 * because it was never about the library. It was about four ways motion goes
 * wrong on a page, and all four are still available.
 *
 * Why this is a test and not a look at the page
 * ---------------------------------------------
 *
 * The machine this was written on reports `prefers-reduced-motion: reduce`, so
 * every browser pass over the public pages exercises the *disabled* path. That is
 * the right path to have verified by eye, and it is the wrong path to conclude
 * anything about whether the motion exists. A page that honours the preference
 * correctly is motionless, and a motionless page looks exactly like a broken one.
 *
 * So these tests read the source instead, and assert the properties that were
 * never in doubt: that a missing script cannot blank a page, that nothing
 * animates a box, that a reduced motion reader still ends up with the content,
 * and that every treatment the views ask for is one the stylesheet defines.
 *
 * The failure that prompted the rewrite
 * --------------------------------------
 *
 * The first version of the new script selected on two of the four treatment
 * values. An `h1` marked `data-motion="heading"` matched neither, so it was never
 * revealed, and the stylesheet's hidden rule held it at zero opacity for ever.
 * The page rendered with no headline on it. Nothing logged, nothing threw, and
 * every accessibility and performance check still passed.
 *
 * The test that would have caught it is the one below asserting that the script's
 * selector covers every value the stylesheet defines.
 */
class ScrollAnimationTest extends TestCase
{
    /**
     * The script and the stylesheet, once each.
     */
    private function script(): string
    {
        return (string) file_get_contents(resource_path('js/app.js'));
    }

    private function stylesheet(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    /**
     * Every treatment any view actually asks for.
     */
    private function treatmentsInUse(): array
    {
        $found = [];

        foreach (glob(resource_path('views/**/*.blade.php')) as $file) {
            preg_match_all('/data-motion="([a-z-]+)"/', (string) file_get_contents($file), $matches);

            $found = array_merge($found, $matches[1]);
        }

        return array_values(array_unique($found));
    }

    public function test_no_animation_library_is_installed(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('package.json')), true);

        $dependencies = array_merge(
            array_keys($composer['dependencies'] ?? []),
            array_keys($composer['devDependencies'] ?? [])
        );

        $libraries = array_values(array_filter(
            $dependencies,
            fn (string $package): bool => in_array(
                strtolower(strtok($package, '/')),
                ['aos', 'gsap', 'animejs', 'motion', 'framer-motion', 'scrollmagic', 'velocity'],
                true
            )
        ));

        $this->assertSame(
            [],
            $libraries,
            'An animation library is installed again. The motion is a keyframe animation and an '
                .'IntersectionObserver, and a library carrying fourteen kilobytes to animate nine '
                .'elements is the reason this file had to be rewritten.'
        );
    }

    public function test_the_shipped_bundle_carries_no_library_markers(): void
    {
        $bundles = glob(public_path('build/assets/app-*.js')) ?: [];

        if ($bundles === []) {
            $this->markTestSkipped('The front end has not been built.');
        }

        foreach ($bundles as $bundle) {
            $this->assertDoesNotMatchRegularExpression(
                '/aos-init|aos-animate|AOS\.init/',
                (string) file_get_contents($bundle),
                basename($bundle).' still carries library markers. Either the import was dropped or '
                    .'the build is stale.'
            );
        }
    }

    public function test_the_script_selects_every_treatment_the_stylesheet_defines(): void
    {
        $script = $this->script();

        /*
         | The selector is a bare attribute, not a value match.
         |
         | This is the assertion that would have caught the invisible headline. A
         | script that names two of the four treatments leaves the other two
         | matched by nothing, unrevealed, and held at zero opacity by the
         | stylesheet that is correctly waiting for a class that never arrives.
         */
        $this->assertStringContainsString(
            "querySelectorAll('[data-motion]')",
            $script,
            'The script does not select on the attribute alone. A value-specific selector leaves any '
                .'treatment it does not name permanently invisible.'
        );
    }

    public function test_an_element_is_never_hidden_unless_the_script_can_reveal_it(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/\[data-motion\]:not\(\.is-revealed\)\s*\{[^}]*opacity:\s*0/',
            $css,
            'The hidden state is not scoped to :not(.is-revealed). An ungated `[data-motion] { opacity: 0 }` '
                .'means a script that never runs leaves every marked element invisible, with nothing in the '
                .'console to explain the blank page.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\[data-motion\]\s*\{[^}]*opacity:\s*0/',
            $css,
            'There is an ungated [data-motion] rule setting opacity to zero.'
        );
    }

    public function test_every_treatment_a_view_asks_for_is_defined(): void
    {
        $css = $this->stylesheet();
        $unknown = [];

        foreach ($this->treatmentsInUse() as $treatment) {
            /*
             | A treatment is either a treatment of one element, written
             | `[data-motion="heading"] {`, or a modifier of a group, written
             | `[data-motion="stagger"] > * {`.
             |
             | The first version of this only accepted the first shape, and
             | reported `stagger` as undefined. It is defined, and it works; the
             | check was simply looking for the wrong shape. A check that fails
             | for a reason unrelated to what it names is worse than no check,
             | because the fix is to the check and not to the code.
             */
            $shapes = [
                '/\[data-motion=[\'"]'.preg_quote($treatment, '/').'[\'"]\]\s*\{/',
                // A group modifier, which only ever styles the children.
                '/\[data-motion=[\'"]'.preg_quote($treatment, '/').'[\'"]\]\s*[>+~]\s*[^{]*\{/',
            ];

            $found = false;

            foreach ($shapes as $shape) {
                if (preg_match($shape, $css) === 1) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                continue;
            }

            $unknown[] = $treatment;
        }

        $this->assertSame(
            [],
            $unknown,
            'A view asks for a treatment the stylesheet does not define: '.implode(', ', $unknown)
                .'. It will be hidden by the base rule and never revealed, because there is no keyframe '
                .'to reveal it with.'
        );
    }

    public function test_every_treatment_a_view_asks_for_is_handled_by_the_script(): void
    {
        $script = $this->script();

        foreach ($this->treatmentsInUse() as $treatment) {
            $handled = $script === ''
                ? false
                : (str_contains($script, 'data-motion') || str_contains($script, 'dataset.motion'));

            $this->assertTrue(
                $handled,
                "The view marks data-motion=\"{$treatment}\" and the script does not read data-motion at all."
            );
        }
    }

    public function test_a_reduced_motion_preference_still_ends_with_the_content_visible(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{.*?\[data-motion[^\{]*\{[^}]*opacity:\s*1\s*!important/s',
            $css,
            'Under reduced motion a marked element is not forced to full opacity. A reader who has asked '
                .'for less motion gets an invisible page instead.'
        );

        $this->assertMatchesRegularExpression(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{.*?animation:\s*none\s*!important/s',
            $css,
            'Under reduced motion the animation is not switched off outright, so the elements are on '
                .'screen and still moving.'
        );

        $script = $this->script();

        $this->assertStringContainsString(
            'prefers-reduced-motion',
            $script,
            'The script does not read the reduced motion preference, so a reader who asks for less motion '
                .'still gets an animation.'
        );

        $this->assertStringContainsString(
            "addEventListener('change'",
            $script,
            'The script reads the preference once and never listens for it changing. A reader who turns '
                .'reduced motion on while the page is open has to reload to escape what is moving.'
        );
    }

    public function test_an_element_on_screen_at_load_is_revealed_by_a_keyframe(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+lms-reveal/',
            $css,
            'The reveal keyframes are missing.'
        );

        $this->assertMatchesRegularExpression(
            '/\[data-motion\]\.is-revealed\s*\{[^}]*animation:\s*lms-reveal/s',
            $css,
            'Nothing applies the reveal keyframes to a revealed element.'
        );

        $this->assertMatchesRegularExpression(
            '/\[data-motion\]\.is-revealed\s*\{[^}]*both/s',
            $css,
            'The reveal does not hold its final value, so an element can be left at an intermediate '
                .'opacity after the animation ends.'
        );

        /*
         | A keyframe rather than a transition, and this is the whole reason the
         | library was removed. A transition needs two painted styles to cross.
         | An element that is on screen when the page loads is given its hidden
         | and its revealed state in the same style recalculation, the browser
         | has one computed style to draw, and nothing ever moves. A keyframe has
         | a start, an end and a length, so it plays from wherever the element is.
         */
        $script = $this->script();

        $this->assertStringContainsString(
            'onScreenAtLoad',
            $script,
            'Nothing checks whether an element is on screen at load, so a hero heading has to wait for a '
                .'scroll that will never come.'
        );
    }

    public function test_no_animation_changes_a_box(): void
    {
        $css = $this->stylesheet();

        /*
         | Only the keyframes are inspected, and the distinction matters.
         |
         | A first version of this scanned the whole motion section for a
         | `height`, and found the two pixel underline beneath a heading link. That
         | value is static: it is set once and never changes, and the animation
         | acting on that element is a `transform` that scales it. The rule was
         | reporting a correct piece of CSS as a layout thrash.
         |
         | What costs layout is a property that changes between two frames, which
         | is only ever true inside `@keyframes`. Checking there asks the question
         | that was actually meant.
         */
        $keyframes = $this->keyframeBlocks($css);

        $this->assertNotSame(
            '',
            $keyframes,
            'No @keyframes were found in the motion section, so this test is passing because it found '
                .'nothing to check rather than because the motion is safe.'
        );

        foreach (['width', 'height', 'top', 'left', 'right', 'bottom', 'margin', 'padding', 'font-size'] as $property) {
            $this->assertDoesNotMatchRegularExpression(
                '/(^|[{;\s])'.$property.':/i',
                $keyframes,
                "An animation changes `{$property}`, so the page reflows while it animates. Only opacity "
                    .'and transform are safe here, because neither needs the layout the others need.'
            );
        }
    }

    public function test_no_animation_promotes_a_layer_permanently(): void
    {
        $css = $this->stylesheet();

        $this->assertDoesNotMatchRegularExpression(
            '/\[data-motion[^\{]*\{[^}]*will-change/s',
            $css,
            'A marked element is permanently promoted to its own compositor layer. Nine large layers held '
                .'for the whole visit is memory the rest of the page does not get, and the cost is paid '
                .'whether or not anything ever animates.'
        );
    }

    public function test_the_heading_and_box_treatments_carry_their_own_movement(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+lms-heading-in/',
            $css,
            'Headings have no keyframe of their own, so a heading animates as a generic box.'
        );

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+lms-box-in/',
            $css,
            'Boxes have no keyframe of their own, so a card arrives as a generic block.'
        );

        $this->assertMatchesRegularExpression(
            '/\[data-motion=[\'"]heading[\'"]\]\.is-revealed\s*\{[^}]*animation-name:\s*lms-heading-in/s',
            $css,
            'The heading treatment does not name the heading keyframes.'
        );

        /*
         | A heading moves in `em`, so the travel scales with the size it is set
         | at. A heading animating fourteen pixels reads as a paragraph that
         | moved, because a headline is much larger than fourteen pixels of travel.
         |
         | The closing bracket is in the pattern. Leaving it out makes the
         | expression expect whitespace where the `]` is, which never matches, and
         | the assertion then fails for a reason that has nothing to do with the
         | thing it claims to check. A test that cannot fail for the right reason
         | is worse than no test, because it is a test that always fails.
         */
        $this->assertMatchesRegularExpression(
            '/\[data-motion=[\'"]heading[\'"]\]\s*\{[^}]*--motion-travel:\s*[\d.]+em/s',
            $css,
            "The heading treatment's travel is not expressed in em, so it does not scale with the heading size."
        );
    }

    public function test_the_hero_heading_is_marked_and_the_home_page_uses_the_new_attribute(): void
    {
        foreach (['home', 'about'] as $page) {
            $markup = (string) file_get_contents(resource_path("views/public/{$page}.blade.php"));

            $this->assertStringContainsString(
                'data-motion="heading"',
                $markup,
                "The {$page} page has no heading reveal, so the one thing a reader sees first arrives "
                    .'without moving.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/data-aos/',
                $markup,
                "The {$page} page still marks motion with the removed library's attribute."
            );
        }
    }

    /**
     * The motion section of the stylesheet, so a layout property elsewhere in the
     * file is not mistaken for one in the animation rules.
     */
    private function motionBlock(string $css): string
    {
        $start = strpos($css, '[data-motion]:not(.is-revealed)');

        if ($start === false) {
            return '';
        }

        /*
         | The block runs to the reduced motion query, which is the last thing in
         | the section. Taking everything up to that point is the honest span;
         | guessing a line count breaks the moment a rule is added.
         */
        $end = strpos($css, '@media (prefers-reduced-motion: reduce)', $start);
        $end = $end === false ? strlen($css) : $end;

        return substr($css, $start, $end - $start);
    }

    /**
     * The bodies of every keyframe in the motion section, concatenated.
     *
     * A property only costs layout if it differs between two frames, which can
     * only happen here. A static `height` on a pseudo-element that is animated by
     * a transform is not a thrash and must not be reported as one.
     */
    private function keyframeBlocks(string $css): string
    {
        if (preg_match_all('/@keyframes\s+[\w-]+\s*\{(.*?)\n\s*\}/s', $this->motionBlock($css), $matches) === 0) {
            return '';
        }

        return implode("\n", $matches[1]);
    }
}
