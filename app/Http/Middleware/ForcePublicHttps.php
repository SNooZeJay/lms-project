<?php

namespace App\Http\Middleware;

use App\Support\PublicHttps;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generate https URLs for requests addressed to the public address.
 *
 * WHY THIS IS MIDDLEWARE AND NOT A PROVIDER
 *
 * It was in `AppServiceProvider::boot()`, and that worked only because the rule
 * was `APP_URL` and nothing else, which is a fact known before any request
 * exists. The rule now also asks what host the request was addressed to, and a
 * provider boots before that is knowable: `request()` during boot is not the
 * request being handled. Forcing the scheme from there would consult an empty
 * request and quietly decide no, on every request, forever.
 *
 * Middleware runs with the real request in hand and still runs before a view is
 * rendered, which is the only requirement: every generated URL, including the
 * stylesheet and the script the layout asks for, is decided before any of it is
 * written into the document.
 *
 * WHAT IT DOES AND DOES NOT FORCE
 *
 * It forces https for the public address and for a connection that is already
 * secure, and it leaves everything else alone. See `App\Support\PublicHttps` for
 * why the local port was being broken by the earlier rule, and for why the host
 * is a safe thing to compare against.
 *
 * `URL::forceScheme` is process wide once set, so a long running worker would
 * carry it into a later request. It is reset to `null` on the way out so the next
 * request decides for itself. Octane and queue workers both keep one process
 * alive across many requests, and a scheme left forced is how a second request to
 * a different host inherits the first one's answer.
 */
class ForcePublicHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cleared first, so a previous request in this process cannot leave the
        // scheme forced for this one.
        URL::forceScheme(null);

        if (PublicHttps::appliesTo($request)) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
