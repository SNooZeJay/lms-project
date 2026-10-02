<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Whether this request should have https URLs generated for it.
 *
 * WHY THE HOST IS THE QUESTION, AND NOT ONLY THE CONFIGURATION
 *
 * The decision used to be `APP_URL` starts with https, and nothing else. That is
 * safe and it is correct for the public address, and it was completely broken for
 * every other way of reaching the same application.
 *
 * A request to `http://127.0.0.1:8000` was given https URLs, so the page asked
 * the browser for `https://127.0.0.1:8000/build/assets/app.css`. That port
 * serves http. The browser cannot verify a certificate for a loopback address, so
 * it discarded the stylesheet, and the page arrived with no design system on it
 * at all: every link, form and control still worked, and none of the styling did.
 * A stylesheet the browser refuses is not a warning, it is a different page.
 *
 * Nothing reported it, which is the part that made it worth fixing properly. The
 * HTML answered 200. Every asset answered 200. A test made with an HTTP client
 * passed, because an HTTP client does not enforce the content security policy
 * that was doing the refusing. Only a browser applying the page saw the fault.
 *
 * SO: THE PUBLIC ADDRESS, OR THE CONNECTION ITSELF
 *
 * https is forced when either of two things is true.
 *
 * The request arrived on https. That is the request telling the truth about
 * itself, and it is the strongest signal there is.
 *
 * Or the request is addressed to the host in APP_URL. That is the public address,
 * and it is the case the original rule was written for. A proxy that terminates
 * TLS forwards plain http, so the request looks like http while the browser is
 * on https, and the host is the only thing that still distinguishes it.
 *
 * WHAT THIS DOES NOT LET ANYBODY DO
 *
 * A client cannot use the second rule to make the application emit https links on
 * a plain connection in a way that matters, because a client that sets the Host
 * header to the public address is asking for the public address, and https links
 * are the correct answer for it. A client that leaves the Host alone gets http
 * links, which is the correct answer for the address it asked for.
 *
 * A header a client sends is not what is being read. `Request::host()` is the
 * host the request was addressed to, and the comparison is against server
 * configuration.
 *
 * The transport security header reads the same answer through `PublicHttps`, so a
 * generated link, a secure cookie and that header still cannot disagree.
 */
final class PublicHttps
{
    /**
     * Whether the public address this application is served on is https.
     *
     * Configuration only, and used for the transport security header, which
     * describes the address rather than the request.
     */
    public static function isEnabled(): bool
    {
        return str_starts_with((string) config('app.url'), 'https://');
    }

    /**
     * Whether this particular request should have https URLs generated for it.
     */
    public static function appliesTo(Request $request): bool
    {
        /*
         | The proxy's own word about the scheme, read directly.
         |
         | This is the branch that actually runs in practice, and until it existed
         | neither of the two branches below it did.
         |
         | `$request->isSecure()` is the obvious way to ask and it does not work.
         | It only reports true when Symfony accepts the forwarded headers, which
         | it does only when the request arrived from a proxy it has been told to
         | trust. Measured, on this application, from a request that carried
         | `X-Forwarded-Proto: https`:
         |
         |   plain request ...................................... http assets
         |   X-Forwarded-Proto: https ........................... http assets  ← ignored
         |   Host set to the address in APP_URL ................ https assets
         |
         | So the tunnel header is present on the request and is not being
         | believed, and the only thing that produced https was the host matching
         | APP_URL. Behind a tunnel that is a coincidence waiting to happen: the
         | tunnel hands out a different random host on every restart, APP_URL does
         | not change, the two stop matching, and the page starts advertising its
         | own stylesheet over http while being served over https. A browser blocks
         | that as mixed active content, so the site arrives with no design system
         | and no JavaScript, and nothing in the response says why.
         |
         | Reading the header off the request object sidesteps the whole question
         | of whose proxy is trusted, and it is the signal both Cloudflare and
         | ngrok actually send. It is placed first and it is not gated on
         | APP_URL: a request that says it arrived over https is telling the truth
         | about itself, and configuration should not get to overrule that.
         */
        if (self::forwardedAsSecure($request)) {
            return true;
        }

        if (! self::isEnabled()) {
            return false;
        }

        // The request is on https already, so https links are simply true.
        if ($request->isSecure()) {
            return true;
        }

        // The request is addressed to the public address, which is what a plain
        // http hop from a TLS terminating proxy looks like from here.
        return self::addressesThePublicHost($request);
    }

    /**
     * Whether a proxy said this request arrived over https.
     *
     * Read from the request rather than from `$request->isSecure()`, and a comma
     * separated list is handled because a chain of proxies appends to the header
     * rather than replacing it. The FIRST value is the one the client used: each
     * proxy appends what it received, so the leftmost is the outermost and
     * therefore the one nearest the browser.
     */
    private static function forwardedAsSecure(Request $request): bool
    {
        $header = $request->server->get('HTTP_X_FORWARDED_PROTO');

        if (! is_string($header) || $header === '') {
            return false;
        }

        $first = strtolower(trim(explode(',', $header)[0]));

        return $first === 'https';
    }

    /**
     * Whether the request was addressed to the host in APP_URL.
     *
     * Compared as hosts rather than as whole addresses, because a port in one and
     * not the other says nothing about the scheme and would make the comparison
     * fail for the ordinary case of the public address arriving on its own port.
     */
    private static function addressesThePublicHost(Request $request): bool
    {
        $public = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($public) || $public === '') {
            return false;
        }

        return strcasecmp($request->getHost(), $public) === 0;
    }
}
