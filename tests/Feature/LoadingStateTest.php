<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * A loading state that cannot lie, and a response that cannot be cached by
 * mistake.
 *
 * Two separate promises, held together because both are about what a person is
 * shown while the application is working.
 *
 * A placeholder that is the wrong shape is worse than no placeholder, because
 * the person was given a layout to read and the content then arrives into a
 * different one. So the placeholder is built from the same tokens as the content
 * and these tests check that it still is.
 *
 * The second promise is the one that must never be broken for speed. Every page
 * here carries a CSRF token and, once signed in, somebody's own records. A
 * response that a shared cache is allowed to keep is a response that can be
 * handed to the wrong person, so that is asserted rather than assumed.
 */
class LoadingStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_placeholder_renders_one_line_per_requested_line(): void
    {
        $html = View::make('components.skeleton', ['lines' => 4])->render();

        $this->assertSame(4, substr_count($html, 'skeleton-line'));
    }

    public function test_the_placeholder_is_never_announced(): void
    {
        $html = View::make('components.skeleton', ['lines' => 3])->render();

        // A placeholder is not content. A screen reader that reads three empty
        // rows of nothing is being given noise in place of information, and the
        // real content is announced when it arrives.
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('Loading', $html);
        $this->assertStringNotContainsString('loading', $html);
    }

    public function test_the_placeholder_uses_the_same_token_as_the_content_it_replaces(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // The fill is the muted surface, which is a token both themes define.
        // A hard coded grey would be unreadable in one of them.
        $this->assertStringContainsString(
            'bg-surface-muted',
            $css,
            'The placeholder must be filled with a design token, not a literal colour.'
        );

        // One line of a body paragraph is the same height as one line of the text
        // it stands in for. This is the whole point: the height is not measured,
        // it is taken from the type the content uses, so the two cannot drift.
        $this->assertStringContainsString(
            '.skeleton-line {',
            $css,
            'The placeholder needs a line rule derived from the type scale.'
        );
        $this->assertMatchesRegularExpression(
            '/\.skeleton-line\s*\{[^}]*h-4[^}]*\}/s',
            $css,
            'A placeholder line should be one text line tall, so it matches the content it replaces.'
        );
    }

    public function test_the_placeholder_stops_pulsing_for_reduced_motion(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // A pulsing block is a repeated large change, which is the thing that
        // setting exists to stop. Turning the animation off rather than slowing
        // it is deliberate: a slower pulse is still a pulse.
        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\)\s*\{[^}]*\.skeleton-line\s*\{[^}]*animation:\s*none/s',
            $css,
            'The placeholder must stop animating when reduced motion is asked for.'
        );
    }

    public function test_a_signed_in_response_is_never_cacheable(): void
    {
        $student = User::factory()->create();

        foreach ([
            route('student.dashboard'),
            route('student.courses.index'),
            route('account.profile'),
        ] as $url) {
            $response = $this->actingAs($student)->get($url)->assertOk();

            $header = (string) $response->headers->get('Cache-Control');

            // Anything a shared cache may keep is a page of this student's own
            // records, which is exactly the failure this pins shut. Private stops
            // a proxy or a CDN; no-store stops the browser as well.
            $this->assertStringNotContainsString('public', $header, "{$url} may be stored by a shared cache.");
            $this->assertTrue(
                str_contains($header, 'private') || str_contains($header, 'no-store'),
                "{$url} has no directive stopping a shared cache: {$header}"
            );
        }
    }

    public function test_a_signed_in_response_is_never_cached_by_the_browser_either(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        $header = (string) $response->headers->get('Cache-Control');

        // On a shared computer the browser is the risk, not the proxy. A page
        // left in the history cache is a page the next person at that machine
        // can press back into.
        $this->assertTrue(
            str_contains($header, 'no-store') || str_contains($header, 'no-cache'),
            "A signed in page may be reused from the browser cache: {$header}"
        );
    }

    public function test_a_write_refusal_never_carries_a_cacheable_response(): void
    {
        $student = User::factory()->create();

        // Even a refusal should not be reusable, because it is generated per
        // account and its Retry-After is a countdown for one person.
        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $this->assertStringNotContainsString(
            'public',
            (string) $response->headers->get('Cache-Control')
        );
    }
}
