<?php

namespace App\Support;

/**
 * Whether the public address this application is served on is https.
 *
 * A TLS terminating proxy forwards plain HTTP, so the request itself looks like
 * http unless the proxy sends X-Forwarded-Proto and the proxy address is
 * trusted. A tunnel does neither reliably, so the request cannot be trusted to
 * answer this. APP_URL can, because it is server configuration and no request
 * can influence it.
 *
 * Everything that has to agree about the public scheme reads it from here, so a
 * generated https link, a secure cookie, and a transport security header can
 * never disagree with each other.
 */
final class PublicHttps
{
    public static function isEnabled(): bool
    {
        return str_starts_with((string) config('app.url'), 'https://');
    }
}
