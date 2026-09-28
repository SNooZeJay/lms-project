<?php

namespace App\Http\Middleware;

use App\Support\PublicHttps;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response.
 *
 * A tunnel means any origin can try to frame these pages, sniff a response, or
 * refer a visitor somewhere else. None of that is interesting on a laptop and
 * all of it is interesting on a public address.
 *
 * The content security policy names a per request nonce instead of allowing
 * inline scripts, because the layout and the payment return page both carry a
 * small inline script and 'unsafe-inline' would give that permission back to any
 * injected script. Views opt in with the nonce attribute, which is shared here
 * as $cspNonce.
 *
 * Transport security is decided through PublicHttps rather than through
 * $request->isSecure(), because a tunnel forwards plain HTTP and the proxy is
 * not trusted, so the request cannot tell that the visitor is on https. Reading
 * APP_URL also keeps this header in step with the scheme used for generated
 * links.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // The nonce is published before the view renders, so it has to be
        // generated before the request is handled rather than after.
        View::share('cspNonce', base64_encode(random_bytes(16)));

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->headers->set('Content-Security-Policy', $this->policy());

        $this->removePhpSignature($response);

        if ($request->isSecure() || PublicHttps::isEnabled()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * Stop the exact PHP version from being published.
     *
     * Removing the header from the response object is not enough on its own.
     * X-Powered-By is written by the PHP SAPI itself, before the framework has
     * built a response, so the header is already in the output and removing it
     * from the Symfony Response only stops the application echoing a second
     * copy. The call to header_remove() is what actually clears the entry PHP
     * recorded, and it is safe to attempt because nothing has been flushed yet.
     *
     * Knowing the version narrows an attacker's search to the advisories that
     * affect that release, so this is treated as a real disclosure rather than
     * cosmetic. The durable fix is expose_php=Off in php.ini, which
     * CheckProductionReadiness reports as a server setting so it is not
     * forgotten at deployment.
     */
    private function removePhpSignature(Response $response): void
    {
        $response->headers->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
    }

    private function policy(): string
    {
        $nonce = View::getShared()['cspNonce'] ?? '';

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "style-src 'self'",
        ];

        if (is_string($nonce) && $nonce !== '') {
            $directives[] = "script-src 'self' 'nonce-{$nonce}'";
        } else {
            $directives[] = "script-src 'self'";
        }

        return implode('; ', $directives);
    }
}
