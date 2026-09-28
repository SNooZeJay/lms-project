<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * A ceiling on writes, applied by verb rather than route by route.
 *
 * Reads are left alone on purpose. A student refreshing a dashboard, or an
 * administrator paging through the activity log, costs one indexed read each and
 * limiting it would produce an error page for something the database handles
 * without trouble. Writes are the operations that take a row lock, move money,
 * or issue a certificate, and those are the ones worth bounding.
 *
 * Deciding by verb means a new write route is covered the moment it is added.
 * Listing a throttle on each route is the version of this that gets forgotten,
 * and the forgotten one is exactly the endpoint somebody discovers with a script.
 *
 * This is a backstop, not the duplicate submission defence. A student who double
 * clicks still gets one enrollment and one progress row, because the database
 * constraints and the row locks in the actions decide that. This keeps a burst
 * from becoming load. It never decides who may do what, and it never returns
 * another person's data.
 *
 * The allowances live here rather than in a rate limit provider because this is
 * the only place they are read. Two files holding the same number is two files
 * that can disagree.
 */
class ThrottleWrites
{
    /**
     * Writes allowed per minute, per person.
     *
     * Every figure leaves a wide margin above what a real person produces by
     * clicking quickly. A burst past one of these is almost always a script, a
     * retry loop, or a browser replaying a request that already succeeded, and
     * all three are things the application would rather answer with a short
     * refusal than with a queue of work.
     */
    private const ALLOWANCE = [
        // Asking for a password reset, registering, confirming a password.
        // These are one-per-person operations, and each one sends an email or
        // writes an account, so they are held to the same number as a sign in
        // attempt. At the general write ceiling of sixty a minute, a single
        // address could be made to receive tens of thousands of reset messages a
        // day, which turns a forgotten password into a way to flood a mailbox.
        'account' => 5,
        // Marking lessons complete, editing a profile, enrolling.
        'write' => 60,
        // Building a module, attaching a material, managing users. An instructor
        // working through a course legitimately clicks a lot in a short burst.
        'curriculum' => 40,
        // Starting and submitting a quiz. A student cannot sit the same quiz
        // faster than a person can read it, and a retried submit is a bug in the
        // client rather than something to allow for.
        'assessment' => 20,
    ];

    /**
     * Route name prefixes that get their own allowance, because the work they do
     * is heavier or the person doing it legitimately clicks faster.
     *
     * @var array<string, string>
     */
    private const NAMED_LIMITS = [
        // The account routes belong to Fortify, so the names are the framework's
        // rather than this application's. They are named here for the same
        // reason the rest are: a route that is not in this list silently falls
        // back to the general write ceiling, which for these is far too generous.
        'register.store' => 'account',
        'password.email' => 'account',
        'password.update' => 'account',
        'password.confirm' => 'account',
        'instructor.courses.modules' => 'curriculum',
        'instructor.courses.materials' => 'curriculum',
        'instructor.courses.quizzes' => 'curriculum',
        'admin.users' => 'curriculum',
        'student.quizzes.attempts' => 'assessment',
    ];

    /**
     * Routes that carry their own throttle and must not be counted twice.
     *
     * The payment provider posts here from its own servers and in bursts, and
     * the ceiling on it is already declared on the route, where the reason for
     * it is visible.
     *
     * @var list<string>
     */
    private const EXEMPT = [
        'webhooks.paymongo',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->writes($request)) {
            return $next($request);
        }

        $name = $request->route()?->getName();

        if ($name !== null && in_array($name, self::EXEMPT, true)) {
            return $next($request);
        }

        $limit = $this->limitFor($name);
        $key = $this->key($request, $limit);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return $this->tooMany($key, $limit);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }

    private function writes(Request $request): bool
    {
        return ! in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function limitFor(?string $routeName): int
    {
        if ($routeName === null) {
            return self::ALLOWANCE['write'];
        }

        foreach (self::NAMED_LIMITS as $prefix => $limit) {
            if (str_starts_with($routeName, $prefix)) {
                return self::ALLOWANCE[$limit];
            }
        }

        return self::ALLOWANCE['write'];
    }

    /**
     * Counted against the account, not the address, whenever there is one.
     *
     * Keying on the address alone would let one student behind a shared
     * connection exhaust the allowance of everyone else behind it, which turns a
     * protective limit into an outage for innocent people. The limit name is part
     * of the key so the three allowances are counted separately rather than
     * sharing one bucket.
     */
    private function key(Request $request, int $limit): string
    {
        $user = $request->user();

        return $user === null
            ? 'write:ip:'.$request->ip()
            : 'write:user:'.$user->getAuthIdentifier().':'.$limit;
    }

    private function tooMany(string $key, int $limit): Response
    {
        $retryAfter = RateLimiter::availableIn($key, $limit);

        return response(
            view('errors/429', ['retryAfter' => $retryAfter]),
            429,
            [
                'Retry-After' => (string) $retryAfter,
                // Tells an intermediary this is a temporary refusal rather than
                // a permanent rejection, so a proxy backs off instead of
                // treating the route as gone.
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Reset' => (string) now()->addSeconds($retryAfter)->getTimestamp(),
            ],
        );
    }
}
