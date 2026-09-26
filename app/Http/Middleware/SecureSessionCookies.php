<?php

namespace App\Http\Middleware;

use App\Support\PublicHttps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the session cookies secure when the public address is https.
 *
 * The framework normally decides this from the request. A tunnel forwards plain
 * HTTP and is not a trusted proxy, so the request looks like http and the
 * framework drops the flag, which means the session cookie would travel in the
 * clear to anyone who can downgrade the connection.
 *
 * This has to run before the session starts, because that is when the cookie is
 * built. The scheme comes from APP_URL through PublicHttps, the same answer that
 * generated links and the transport security header use, so the three cannot
 * disagree.
 */
class SecureSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PublicHttps::isEnabled()) {
            config(['session.secure' => true]);
        }

        return $next($request);
    }
}
