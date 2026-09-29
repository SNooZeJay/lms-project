<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The rotating typewriter line on the authentication panel.
 *
 * The effect is the one part of the sign in and sign up pages that only exists
 * when a script runs, which is exactly the kind of feature that fails quietly.
 * A guard that is too eager leaves a static line that looks like a plain
 * paragraph, so the panel has to keep the same information in the markup and the
 * loop has to keep the properties that make it read as a typewriter.
 */
class AuthPanelRotatingLineTest extends TestCase
{
    private function bundleSource(): string
    {
        $scripts = glob(public_path('build/assets/app-*.js')) ?: [];
        $source = implode("\n", array_map('file_get_contents', $scripts));

        $this->assertNotSame('', $source, 'The bundled script was not found. Run the asset build first.');

        return $source;
    }

    public function test_the_panel_carries_three_sentences_for_the_line_to_rotate(): void
    {
        $body = (string) $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-rotate-sentences/', $body);
        $this->assertMatchesRegularExpression('/data-sentences="[^"]+(\|\|[^"]+){2,}"/', $body);
    }

    public function test_the_first_sentence_is_in_the_markup_so_the_line_is_never_empty(): void
    {
        // With the script blocked the element still has to read as a sentence.
        // This is the difference between a plain line of text and a broken gap.
        foreach (['/login', '/register'] as $path) {
            $body = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('Free courses are open to anyone.', $body);
        }
    }

    public function test_the_animated_line_is_hidden_from_assistive_technology(): void
    {
        // Rewriting the text every few dozen milliseconds would be read out as
        // noise, so the visible line is hidden and the sentences are also
        // rendered as a plain list for anything that reads the page.
        $body = (string) $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/data-rotate-sentences[^>]*aria-hidden="true"/s',
            $body,
            'The animated line must be hidden from assistive technology.'
        );

        $this->assertMatchesRegularExpression('/<ul class="sr-only">/', $body);
    }

    public function test_all_three_sentences_are_available_without_the_animation(): void
    {
        $body = (string) $this->get('/login')->assertOk()->getContent();

        foreach ([
            'Free courses are open to anyone.',
            'Your progress is saved',
            'a certificate is issued in your name',
        ] as $sentence) {
            $this->assertStringContainsString($sentence, $body);
        }
    }

    public function test_the_block_reserves_its_height_so_swapping_cannot_shift_the_panel(): void
    {
        // The column is a fixed width and the type scales with the viewport, so
        // the longest sentence wraps to the same number of lines at every screen
        // size. Reserving exactly that many lines is what stops a short sentence
        // pulling the message underneath it upward on every swap.
        $body = (string) $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/data-rotate-sentences[^>]*min-h-\[calc\(4\.6\*clamp\(28px,3vw,38px\)\)\]/s',
            $body,
            'The rotating line must reserve the height of its longest sentence.'
        );
    }

    public function test_the_rotation_behaviour_ships_in_the_bundled_script(): void
    {
        $this->assertStringContainsString('data-rotate-sentences', $this->bundleSource());
    }

    /**
     * The rotating line is not gated on a media query or a width.
     *
     * This is the fault that hid the effect three times. A guard on
     * prefers-reduced-motion, or on viewport width, meant the panel showed a
     * static line that looked like a plain paragraph. The typewriter is what was
     * asked for, so it runs for everyone.
     *
     * It used to be asserted against the whole bundle, which forbade the string
     * `prefers-reduced-motion` appearing anywhere in the shipped script. That is a
     * blunt instrument: it was really a statement about one function, and it went
     * on to forbid a guard in a completely unrelated part of the same file. The
     * scroll animation uses one, and it is the correct one, because a band that
     * settles in as you scroll is a preference about motion and a counter that
     * counts up is too. The typewriter is neither: it is a line of text changing,
     * and gating it removes the thing it exists to do.
     *
     * So the assertion is now scoped to the function that does the rotating. It is
     * read out of the source rather than the bundle, because a minified bundle has
     * no reliable function boundaries and slicing one out of it would be guesswork.
     * A stricter test about the wrong scope is worse than no test, because it gets
     * changed rather than obeyed.
     */
    /**
     * The rotating line is not gated on a media query or a width.
     *
     * This is the fault that hid the effect three times. A guard on
     * prefers-reduced-motion, or on viewport width, meant the panel showed a
     * static line that looked like a plain paragraph. The typewriter is what was
     * asked for, so it runs for everyone.
     *
     * It used to be asserted against the whole bundle, which forbade the string
     * appearing anywhere in the shipped script. That is a blunt instrument: it was
     * really a statement about one function, and it went on to forbid a guard in an
     * unrelated part of the same file.
     *
     * The scroll animation uses one, and it is the correct one. A band settling in
     * as it is scrolled to is a preference about motion, and a figure counting up
     * is too. The typewriter is neither: it is a line of text changing, and
     * gating it removes the thing it exists to do. A test that cannot tell those
     * apart gets changed rather than obeyed, which is worse than no test.
     *
     * So the assertion is scoped to the function that does the rotating, read out
     * of the source rather than the bundle, because a minified bundle has no
     * reliable function boundaries and cutting one out of it would be guesswork.
     */
    public function test_the_rotation_is_not_gated_on_a_media_query(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        $start = strpos($source, 'const rotateSentences');

        $this->assertNotFalse(
            $start,
            'The rotating line function was not found in resources/js/app.js, so this test proved nothing.'
        );

        $slice = $this->declarationAt($source, (int) $start);

        $this->assertStringNotContainsString(
            'prefers-reduced-motion',
            $slice,
            'The rotating line must not be gated on prefers-reduced-motion, or the panel shows a '
            .'static line that looks like a plain paragraph. This is the fault the test was written for.'
        );

        $this->assertStringNotContainsString(
            'min-width',
            $slice,
            'The rotating line must not be gated on a viewport width.'
        );

    }

    /**
     * The one declaration that starts at an offset, up to its own end.
     *
     * Brace matched from the opening one, skipping braces inside strings and
     * comments. A plain count that does not skip them closes on the first brace in
     * a comment, and a slice that ends inside a sentence is a slice about
     * something else.
     *
     * Slicing to the next top level `const` was tried first and reached to the end
     * of the file, because this function is the last `const` before the motion
     * module. The slice then contained an unrelated media query and the test
     * failed on code it was never about.
     */
    private function declarationAt(string $source, int $offset): string
    {
        $open = strpos($source, '{', $offset);

        if ($open === false) {
            return substr($source, $offset);
        }

        $depth = 0;
        $length = strlen($source);
        $state = 'code';
        $quote = '';

        for ($i = $open; $i < $length; $i++) {
            $char = $source[$i];
            $next = $source[$i + 1] ?? '';

            if ($state === 'code') {
                if ($char === '/' && $next === '/') {
                    $state = 'line comment';
                    $i++;

                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $state = 'block comment';
                    $i++;

                    continue;
                }

                if ($char === "'" || $char === '"' || $char === '`') {
                    $state = 'string';
                    $quote = $char;

                    continue;
                }

                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;

                    if ($depth === 0) {
                        return substr($source, $offset, $i - $offset + 1);
                    }
                }
            } elseif ($state === 'line comment') {
                if ($char === "\n") {
                    $state = 'code';
                }
            } elseif ($state === 'block comment') {
                if ($char === '*' && $next === '/') {
                    $state = 'code';
                    $i++;
                }
            } elseif ($state === 'string') {
                if ($char === '\\') {
                    $i++;

                    continue;
                }

                if ($char === $quote) {
                    $state = 'code';
                }
            }
        }

        return substr($source, $offset);
    }
}
