<?php

namespace Tests\Feature\Phase12;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The framework skips CSRF checks while the test suite runs, so a feature test
 * posting to the webhook passes whether or not the path is exempt. That is how
 * a webhook that returned 419 to every real delivery could sit behind a fully
 * green suite.
 *
 * These tests therefore check the exemption list against the real route table
 * instead of relying on a request.
 */
class WebhookCsrfExemptionTest extends TestCase
{
    public function test_the_webhook_route_is_exempt_from_csrf(): void
    {
        $this->assertTrue(
            $this->pathIsExempt('webhooks/paymongo'),
            'webhooks/paymongo must be exempt from CSRF, otherwise every provider delivery returns 419.'
        );
    }

    public function test_every_exempt_path_matches_a_registered_route(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->unique()
            ->values();

        $exempt = config('lms.csrf_exempt_paths', []);

        $this->assertIsArray($exempt);
        $this->assertNotEmpty($exempt, 'A webhook endpoint exists, so the list cannot be empty.');

        foreach ($exempt as $pattern) {
            $matched = $registered->contains(
                fn (string $uri): bool => Str::is($pattern, $uri)
            );

            $this->assertTrue(
                $matched,
                "config/lms.php exempts '{$pattern}' but no registered route matches it."
            );
        }
    }

    public function test_a_normal_form_post_is_not_exempt(): void
    {
        // A broad pattern would quietly disable CSRF for the whole site, which
        // is the opposite of the intent.
        $this->assertFalse($this->pathIsExempt('login'));
        $this->assertFalse($this->pathIsExempt('student/courses/1/enroll'));
    }

    public function test_the_exempt_list_is_not_a_catch_all(): void
    {
        $exempt = config('lms.csrf_exempt_paths', []);

        $this->assertNotContains('*', $exempt, 'Exempting every path removes CSRF protection site wide.');
    }

    private function pathIsExempt(string $uri): bool
    {
        foreach (config('lms.csrf_exempt_paths', []) as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}
