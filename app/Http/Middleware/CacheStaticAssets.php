<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Long lived caching for the built assets, and a short one for everything else.
 *
 * Written because Lighthouse reported no `Cache-Control` on a stylesheet at all,
 * which is the default for a Laravel application serving a file from `public`.
 * Nothing here is misconfigured; the header is simply absent, so a returning
 * visitor downloads the same eighty five kilobytes of CSS and the same thirteen
 * of JavaScript on every single page view. It is the cheapest real win available
 * to this application.
 *
 * WHY THE TWO CASES DIFFER SO MUCH
 *
 * A built asset carries a content hash in its filename. `app-CcnLoUiz.css` is a
 * different file the moment a single byte of source changes, so a cache can keep
 * it for a year and be wrong never. A year is the usual advice and it is safe
 * here precisely because of the hash, which is the whole reason Vite puts one
 * there.
 *
 * A document does not. `public/index.php` is the same URL for every version of
 * the application, and its content is whatever the reader is allowed to see. A
 * long cache on it would serve one person's dashboard to the next person. So
 * documents get a short window, and they revalidate: a conditional request costs
 * a few hundred bytes and returns 304, which is the point.
 *
 * WHAT IS DELIBERATELY NOT CACHED
 *
 * Anything signed in sees private content. `no-store` on a document for an
 * authenticated reader is not a performance nicety, it is what stops a shared
 * machine showing the previous person's dashboard out of the back button, and it
 * is why this is not left to a blanket rule.
 */
class CacheStaticAssets
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = $request->path();

        /*
         | The built assets and the images and fonts beside them.
         |
         | Anything under `build` is content addressed, so it can be kept
         | indefinitely. `immutable` tells the browser not to even offer to
         | revalidate it, which is what saves the round trip rather than the
         | bytes.
         */
        if (str_starts_with($path, 'build/')
            || str_starts_with($path, 'images/')
            || str_starts_with($path, 'icons/')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

            return $response;
        }

        /*
         | A signed in reader.
         |
         | The response is personal, so it is not stored at all. This runs before
         | the shared case below, because a public cache in front of a private
         | document is the failure this whole class exists to prevent.
         */
        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        }

        /*
         | A public document.
         |
         | Short, and revalidated. A minute of freshness with an `stale-while-
         | revalidate` tail means a visitor who follows a link twice in a minute
         | is served from memory, and everyone else pays for one conditional
         | request instead of a full document.
         */
        $response->headers->set(
            'Cache-Control',
            'public, max-age=60, stale-while-revalidate=300'
        );

        return $response;
    }
}
