<?php

namespace Tests\Feature;

use App\Support\PublicHttps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The scheme a generated URL is given, behind a tunnel and without one.
 *
 * WHY THIS FILE EXISTS
 *
 * The site was served over https by a tunnel and advertised its own stylesheet
 * and script over http. A browser blocks those as mixed active content, so the
 * page arrived with no design system and no JavaScript: every link, form and
 * button worked and none of the styling did.
 *
 * Nothing reported it, and the reason is worth writing down. Every check that
 * could be written passed:
 *
 *   the page answered 200
 *   the stylesheet answered 200
 *   an HTTP client fetched the stylesheet without complaint
 *   a test made with Laravel's test client passed
 *
 * An HTTP client does not enforce mixed content. Only a browser applying the page
 * sees the fault, which is why this file makes requests through the same entry
 * point a browser uses and then reads the scheme out of the HTML.
 *
 * THE ACTUAL CAUSE
 *
 * `$request->isSecure()` never became true. It only reports true when Symfony
 * accepts the forwarded headers, which it only does for a proxy it has been told
 * to trust, and behind the tunnel it did not. The one branch that did work was the
 * comparison against the host in `APP_URL`, which is a coincidence that stops
 * working the moment the tunnel hands out a different random host.
 *
 * So these tests are about the header, not about configuration. A test that sets
 * APP_URL correctly proves nothing, because APP_URL is the thing that was wrong.
 */
class PublicHttpsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A request with the tunnel's header must be answered over https.
     *
     * The host deliberately does not match APP_URL, because matching it is the
     * coincidence that masked the fault.
     */
    public function test_a_tunnel_request_is_answered_over_https(): void
    {
        $response = $this->get('/', [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_HOST' => 'some-random-tunnel.trycloudflare.com',
        ]);

        $response->assertOk();

        $this->assertSame([], $this->insecureAssets($response->getContent()));
    }

    /**
     * And the rule that would have caught it: nothing on an https page may be
     * advertised over http.
     */
    public function test_no_page_served_over_a_tunnel_advertises_an_asset_over_http(): void
    {
        foreach (['/', '/login', '/catalog'] as $path) {
            $content = $this->get($path, [
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_HOST' => 'some-random-tunnel.trycloudflare.com',
            ])->getContent();

            $this->assertSame(
                [],
                $this->insecureAssets($content),
                'An asset is advertised over http on '.$path.', which a browser blocks as mixed content.',
            );
        }
    }

    /**
     * The local port must not be broken by the fix.
     *
     * This is the failure the class was originally written to avoid: forcing
     * https for `http://127.0.0.1:8000` makes the page ask that port for a
     * stylesheet over TLS, the browser cannot verify a certificate for a loopback
     * address, and the design system is discarded. A fix for the tunnel that
     * reintroduces that is not a fix.
     */
    public function test_a_plain_local_request_is_still_answered_over_http(): void
    {
        $content = $this->get('/', ['HTTP_HOST' => '127.0.0.1:8000'])->getContent();

        $this->assertNotSame([], $this->httpAssets($content), 'The loopback port serves http and must be asked over http.');
        $this->assertSame([], $this->httpsAssets($content), 'Nothing on a plain local page should be https.');
    }

    /**
     * A proxy chain appends to the header, so the first value is the one the
     * client used and the one nearest the browser.
     */
    public function test_the_leftmost_forwarded_scheme_wins(): void
    {
        // A browser on https, one proxy appending what it saw.
        $this->assertTrue($this->appliesTo('https, http'));

        // A browser on http cannot claim https by putting it first, because the
        // only header this reads is the one a proxy writes, and a proxy writes
        // what it received.
        $this->assertFalse($this->appliesTo('http, https'));
    }

    /**
     * Uppercase, padding, and a chain, because a header is bytes off the wire.
     */
    public function test_the_header_is_compared_without_regard_to_case_or_padding(): void
    {
        $this->assertTrue($this->appliesTo('HTTPS'));
        $this->assertTrue($this->appliesTo('  https  '));

        // A chain: the leftmost is the browser's own scheme, and the rest are
        // hops. `https,http` means a browser on https reached through one proxy
        // that forwarded plain http, which is the ordinary tunnel case.
        $this->assertTrue($this->appliesTo('https,http'));

        $this->assertFalse($this->appliesTo('http, https'));
    }

    /**
     * No header, no claim. Absence is not consent.
     */
    public function test_a_request_with_no_forwarded_header_is_not_claimed_to_be_secure(): void
    {
        $request = Request::create('http://127.0.0.1:8000/');

        $this->assertFalse(PublicHttps::appliesTo($request));
    }

    /**
     * Does the header alone settle it, with APP_URL pointing somewhere else
     * entirely?
     *
     * This is the test that names the fault. Configuration must not be able to
     * overrule a request that is telling the truth about itself.
     */
    public function test_the_header_settles_it_without_help_from_configuration(): void
    {
        config(['app.url' => 'https://a-completely-different-host.example']);

        $request = Request::create('http://127.0.0.1:8000/');
        $request->server->set('HTTP_X_FORWARDED_PROTO', 'https');

        $this->assertTrue(
            PublicHttps::appliesTo($request),
            'A tunnel saying https must be believed even when APP_URL names another host.',
        );
    }

    private function appliesTo(string $forwardedProto): bool
    {
        $request = Request::create('http://127.0.0.1:8000/');
        $request->server->set('HTTP_X_FORWARDED_PROTO', $forwardedProto);

        return PublicHttps::appliesTo($request);
    }

    /**
     * @return list<string>
     */
    private function insecureAssets(string $html): array
    {
        return $this->assetsMatching($html, 'http://');
    }

    /**
     * @return list<string>
     */
    private function httpAssets(string $html): array
    {
        return $this->assetsMatching($html, 'http://');
    }

    /**
     * @return list<string>
     */
    private function httpsAssets(string $html): array
    {
        return $this->assetsMatching($html, 'https://');
    }

    /**
     * Every built asset URL in the document, filtered by scheme.
     *
     * The regex is deliberately loose about everything except the scheme and the
     * path, because the thing being asserted is that no stylesheet or script on
     * an https page is asked for over http.
     *
     * @return list<string>
     */
    private function assetsMatching(string $html, string $scheme): array
    {
        preg_match_all('/(https?:\/\/[^"\']*\/build\/assets\/[^"\']+)/', $html, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $url): bool => str_starts_with($url, $scheme),
        )));
    }
}
