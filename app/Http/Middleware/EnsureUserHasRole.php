<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $userRole = $user?->profile?->role;
        $allowedRoles = array_map(
            static fn (string $role): string => $role,
            $roles,
        );

        abort_unless(
            $userRole instanceof UserRole && in_array($userRole->value, $allowedRoles, true),
            403,
        );

        return $next($request);
    }
}
