<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the debug error page for the developer and away from the public.
 *
 * This project is reached over a tunnel, so the application is on the public
 * internet. With debug on, a failed request renders a page carrying the server
 * file path, the stack trace, source code, the request, and the values of the
 * environment, which on this project includes the database password, the
 * application encryption key, and the payment provider credentials. That is the
 * whole system handed to whoever asked for a broken URL.
 *
 * The rule is deliberately narrow: the debug page is served only when the
 * request arrived directly on the loopback address with no proxy headers at all.
 * A tunnel is always a proxy, so anything arriving through one loses the debug
 * page.
 *
 * The check fails closed. It refuses on the mere presence of a forwarded
 * header rather than trusting its value, because a tunnel that forwards a
 * client supplied X-Forwarded-For would otherwise let anyone claim to be
 * localhost by sending the header themselves.
 */
class ConfineDebugOutput
{
    /**
     * Headers a proxy uses to describe the original client.
     *
     * The presence of any of them means this request was relayed, so the client
     * address cannot be taken at face value for this decision.
     *
     * @var list<string>
     */
    private const PROXY_HEADERS = [
        'Forwarded',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Port',
        'X-Forwarded-Proto',
        'X-Real-IP',
        'Client-IP',
    ];

    /** @var list<string> */
    private const LOOPBACK_ADDRESSES = ['127.0.0.1', '::1'];

    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('app.debug') && ! $this->arrivedDirectlyOnLoopback($request)) {
            config(['app.debug' => false]);
        }

        return $next($request);
    }

    private function arrivedDirectlyOnLoopback(Request $request): bool
    {
        foreach (self::PROXY_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        $address = $request->ip();

        return $address !== null && in_array($address, self::LOOPBACK_ADDRESSES, true);
    }
}
