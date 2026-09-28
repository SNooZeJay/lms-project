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

    public function test_the_rotation_is_not_gated_on_a_media_query(): void
    {
        // This is the fault that hid the effect three times. A guard on
        // prefers-reduced-motion, or on viewport width, meant the panel showed a
        // static line that looked like a plain paragraph. The typewriter is what
        // was asked for, so it runs for everyone.
        //
        // If a guard is ever wanted back, this is the test to change, and the
        // reason belongs in the commit that does it.
        $source = $this->bundleSource();

        $this->assertStringNotContainsString('prefers-reduced-motion', $source);
        $this->assertStringNotContainsString('min-width: 1024px', $source);
    }
}
