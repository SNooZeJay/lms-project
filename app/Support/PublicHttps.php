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
