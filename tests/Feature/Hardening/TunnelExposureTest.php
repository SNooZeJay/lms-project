<?php

namespace Tests\Feature\Hardening;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * This project is served on a public https address through a tunnel, which
 * makes every page reachable by anyone on the internet.
 *
 * The dangerous part is not the tunnel. It is that a tunnel makes a development
 * configuration public. These tests cover the two settings that turn a public
 * URL into a public disclosure: the debug error page, which prints environment
 * values, and the absence of browser security headers.
 *
 * Assertions on an error page compare booleans rather than the page itself. A
 * debug page is a few hundred kilobytes of markup, and a string assertion that
 * fails would try to render all of it as a diff.
 */
class TunnelExposureTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_MESSAGE = 'synthetic audit probe';

    /**
     * A route that always fails, so the error page can be inspected.
     */
    private function routeThatThrows(): void
    {
        Route::middleware('web')->get('/__audit__/boom', function (): void {
            throw new RuntimeException(self::PROBE_MESSAGE);
        });
    }

    private function errorPageFor(string $url, array $headers = [], ?string $remoteAddress = null): string
    {
        $this->routeThatThrows();

        // The test client reports loopback unless the client address is set
        // explicitly, and the client address is the whole question here.
        if ($remoteAddress !== null) {
            $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress]);
        }

        $response = $this->get($url, $headers);

        $response->assertStatus(500);

        return (string) $response->getContent();
    }

    // ---------------------------------------------------------------- debug

    public function test_a_local_request_keeps_the_debug_error_page(): void
    {
        config(['app.debug' => true]);

        $html = $this->errorPageFor('http://127.0.0.1/__audit__/boom');

        $this->assertTrue(
            str_contains($html, self::PROBE_MESSAGE),
            'The developer must still get a usable error page on the loopback address.'
        );
    }

    public function test_a_request_through_a_tunnel_forces_debug_off(): void
    {
        config(['app.debug' => true]);

        // A public client address is what a tunnel delivers, because the tunnel
        // is not a trusted proxy.
        $html = $this->errorPageFor('http://203.0.113.9/__audit__/boom', [], '203.0.113.9');

        $this->assertFalse(
            str_contains($html, self::PROBE_MESSAGE),
            'A public error page must not describe the failure.'
        );
    }

    public function test_a_forwarded_header_cannot_buy_the_debug_page(): void
    {
        config(['app.debug' => true]);

        // Loopback plus a forwarded header is the shape an attacker sends to a
        // tunnel that forwards the header instead of overwriting it. If the
        // header were trusted, this would look local and print every secret.
        $html = $this->errorPageFor('http://127.0.0.1/__audit__/boom', [
            'X-Forwarded-For' => '127.0.0.1',
            'X-Forwarded-Proto' => 'https',
        ]);

        $this->assertFalse(
            str_contains($html, self::PROBE_MESSAGE),
            'A forwarded header must never restore the debug page.'
        );
    }

    public function test_a_public_error_page_never_prints_an_environment_secret(): void
    {
        $canary = 'audit-canary-a3f9';

        config([
            'app.debug' => true,
            'services.paymongo.secret_key' => $canary,
            'services.paymongo.webhook_secret' => $canary,
        ]);

        $html = $this->errorPageFor('http://203.0.113.9/__audit__/boom', [], '203.0.113.9');

        $this->assertFalse(
            str_contains($html, $canary),
            'A public error page leaked a configured secret.'
        );
        $this->assertFalse(
            str_contains($html, base_path()),
            'A public error page leaked the server file path.'
        );
    }

    // --------------------------------------------------------------- headers

    public function test_every_response_carries_the_browser_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotSame('', $response->headers->get('Content-Security-Policy'));
        $this->assertNotSame('', $response->headers->get('Permissions-Policy'));
    }

    public function test_no_response_advertises_the_php_version(): void
    {
        foreach (['/', '/login', '/courses'] as $path) {
            $this->get($path)->assertHeaderMissing('X-Powered-By');
        }
    }

    public function test_the_content_security_policy_does_not_allow_a_remote_script_source(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString('script-src *', $policy);
        $this->assertStringNotContainsString("script-src 'self' *", $policy);
        $this->assertStringNotContainsString('script-src http', $policy);
    }

    public function test_the_content_security_policy_does_not_allow_framing(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function test_a_strict_transport_security_header_is_sent_over_https(): void
    {
        config(['app.url' => 'https://audit.example.test']);

        $this->get('https://audit.example.test/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_session_cookie_is_secure_on_a_public_https_address(): void
    {
        // The tunnel forwards plain HTTP and is not a trusted proxy, so the
        // request looks like http and the framework would drop the secure flag.
        // A transport security header that says always https while the session
        // cookie says http is fine is a contradiction an attacker can use.
        config(['app.url' => 'https://audit.example.test']);

        $response = $this->get('https://audit.example.test/');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c): bool => str_contains((string) $c->getName(), 'session'));

        $this->assertNotNull($cookie, 'The response must set a session cookie.');
        $this->assertTrue(
            $cookie->isSecure(),
            'The session cookie must be secure when the public address is https.'
        );
    }

    public function test_the_session_cookie_is_not_secure_on_a_plain_http_address(): void
    {
        // Otherwise a developer running on http cannot sign in at all, because
        // the browser refuses to return a secure cookie over http.
        config(['app.url' => 'http://audit.example.test']);

        $response = $this->get('http://audit.example.test/');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c): bool => str_contains((string) $c->getName(), 'session'));

        $this->assertNotNull($cookie, 'The response must set a session cookie.');
        $this->assertFalse(
            $cookie->isSecure(),
            'A plain http address must not ask for a secure cookie, or local development breaks.'
        );
    }

    // ------------------------------------------------------- tunnel specific

    public function test_the_webhook_is_throttled_because_it_is_public(): void
    {
        // The webhook is the one endpoint an anonymous stranger may hammer, and
        // it is public because a tunnel has to be. Without a throttle, anyone
        // can hold the signing work busy and starve a real delivery.
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn ($candidate): bool => $candidate->uri() === 'webhooks/paymongo'
                && in_array('POST', $candidate->methods(), true)
        );

        $this->assertNotNull($route, 'The webhook route must exist.');

        $middleware = collect($route->gatherMiddleware())
            ->map(fn ($m): string => is_string($m) ? $m : get_debug_type($m));

        $this->assertTrue(
            $middleware->contains(fn (string $m): bool => str_starts_with($m, 'throttle:')),
            'The webhook must be throttled. Found: '.$middleware->implode(', ')
        );
    }

    public function test_the_student_password_is_hashed_at_rest(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame('password', $user->password);
        $this->assertStringStartsWith('$', $user->password);
    }
}
