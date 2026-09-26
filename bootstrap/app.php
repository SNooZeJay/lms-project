<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'password.change' => RequirePasswordChange::class,
            'role' => EnsureUserHasRole::class,
        ]);

        // Behind a reverse proxy the application must trust the proxy headers,
        // otherwise every generated URL would be http and a secure cookie
        // would be dropped. Trust only the load balancer, never any proxy.
        $middleware->trustProxies(
            at: array_values(array_filter(
                array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))
            )),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // A production release must never serve a cached page to the wrong
        // person. Private responses carry the Store instruction.
        $middleware->encryptCookies(except: [
            // The PayMongo checkout returns through a provider redirect, so no
            // application cookie is needed on that request.
        ]);

        // The payment provider posts to the webhook from its own servers. It
        // holds no session and cannot read a token, so CSRF protection would
        // reject every delivery with a 419. The request is authenticated by
        // the provider signature instead, which is checked on every call.
        //
        // The file is read directly because this callback runs before the
        // config repository is bound, so the config helper is not available.
        /** @var array{csrf_exempt_paths?: list<string>} $lms */
        $lms = require __DIR__.'/../config/lms.php';

        $middleware->validateCsrfTokens(except: $lms['csrf_exempt_paths'] ?? []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
