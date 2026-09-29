<?php

namespace Tests\Feature\Phase15;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The scheme of every URL this application generates.
 *
 * THE RULE THESE TESTS PIN
 *
 * https is forced for a request that is already secure, and for a request
 * addressed to the host in APP_URL. Everything else is left alone.
 *
 * The rule used to be `APP_URL` and nothing else. That is a fact known before any
 * request exists, so it was decided in the service provider, and it was right for
 * the public address and wrong for every other way of reaching the same
 * application.
 *
 * A request to the plain-http local port was handed https URLs. The browser then
 * asked a port that serves http for the stylesheet over https, could not verify a
 * certificate for a loopback address, and discarded it. The page arrived
 * completely unstyled with every link and form still working.
 *
 * Nothing in the suite could see that. The response was 200. The asset itself was
 * reachable, and answered 200 to anything that asked. The refusal came from the
 * browser's own policy, after both. A test made with an HTTP client does not
 * enforce the content security policy, so the fault was invisible to every check
 * this project had, and the first person to see it was a person looking at a
 * screenshot.
 *
 * The proxy case is why the host had to come back into the rule. A TLS
 * terminating proxy forwards plain http, so a request through one looks insecure
 * however it is configured here, and the host is the only thing that still says
 * which address the reader typed.
 */
class HttpsUrlGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The scheme is process wide once forced, so it is cleared either side of
        // every test here. A test that left it set would decide the next one.
        URL::forceScheme(null);
    }

    protected function tearDown(): void
    {
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_an_https_app_url_generates_https_urls(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->get('http://lms.example.com/courses')->assertOk();

        // Only the scheme is asserted. The host comes from the current request
        // or from the cached root, which is not what this test is about.
        $this->assertStringStartsWith(
            'https://',
            route('courses.index'),
            'An https APP_URL must produce https links, or the browser blocks the assets.'
        );
    }

    public function test_a_local_http_app_url_is_left_alone(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->get('http://127.0.0.1:8000/courses')->assertOk();

        $this->assertStringStartsWith(
            'http://',
            route('courses.index'),
            'A local http APP_URL must not be upgraded, or development breaks.'
        );
    }

    /**
     * The fault the change exists for.
     *
     * An https APP_URL, and a request to the local port instead. The local port
     * serves http and holds no certificate, so an https asset URL is one the
     * browser cannot use.
     *
     * The assertion is on the address the layout writes into the document, which
     * is the one the browser actually tries to fetch. Asserting on `route()` would
     * not have caught it: the same fault produced a perfectly well formed https
     * route helper and an unstyled page.
     */
    public function test_a_local_port_is_not_handed_https_urls_by_an_https_app_url(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $body = (string) $this->get('http://127.0.0.1:8000/')->assertOk()->getContent();

        preg_match('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $body, $matches);

        $this->assertNotEmpty($matches, 'The layout wrote no stylesheet link, so this test proved nothing.');

        $this->assertStringStartsWith(
            'http://',
            $matches[1],
            'The stylesheet was requested over https from a port that serves http. The browser '
            .'discards it, and the page arrives with no design system on it at all.'
        );
    }

    /**
     * A request on https already needs no configuration to be believed.
     */
    public function test_a_secure_request_generates_https_urls_regardless_of_the_app_url(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->get('https://lms.example.com/courses')->assertOk();

        $this->assertStringStartsWith('https://', route('courses.index'));
    }

    /**
     * The scheme must not leak between requests inside one process.
     *
     * `URL::forceScheme` is process wide. Under Octane or a queue worker one
     * process serves many requests, and a scheme left forced after the first one
     * is how the second request to a different host inherits the first one's
     * answer. A local request arriving after a public one would be handed https
     * URLs, which is the fault this file's middle test exists to prevent.
     */
    public function test_a_local_request_after_a_public_one_is_not_upgraded(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->get('http://lms.example.com/courses')->assertOk();

        $this->assertStringStartsWith('https://', route('courses.index'));

        $this->get('http://127.0.0.1:8000/courses')->assertOk();

        $this->assertStringStartsWith(
            'http://',
            route('courses.index'),
            'A forced scheme leaked from the previous request. One process serves both addresses.'
        );
    }

    public function test_routes_still_resolve_after_forcing_the_scheme(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->get('http://lms.example.com/courses')->assertOk();

        $matched = Route::getRoutes()->match(
            Request::create('https://lms.example.com/courses', 'GET')
        );

        // assertInstanceOf rather than assertTrue: match() returns a Route, and
        // asserting a boolean on an object is a type error rather than a failure,
        // which is a confusing way for this test to ever go wrong.
        $this->assertInstanceOf(
            RoutingRoute::class,
            $matched,
            'No route matched /courses.'
        );
    }
}
