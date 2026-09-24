<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->profile?->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('account.password', 'account.password.update', 'logout') || $request->routeIs('verification.*')) {
            return $next($request);
        }

        return redirect()
            ->route('account.password')
            ->with('status', 'Change your temporary password to continue.');
    }
}
