<?php

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Asks every readable route the same question as a guest, a student, an
 * instructor and an administrator, and reports any that answered with a page the
 * account should not have been given.
 *
 * Written as a tool rather than a test because the answer is not a yes or no. A
 * student's course list is *supposed* to render for a student and refuse an
 * instructor, and a guest is supposed to be sent to sign in. The defect is a
 * route answering 200 to somebody who has no business seeing it, and that only
 * shows up if every route is asked as every role. Nobody does that by hand across
 * 108 routes.
 *
 * It also reports the routes that answered 404 or 405, which is the more
 * interesting half. A route behind `role:administrator` whose identifiers do not
 * resolve tells you nothing about the middleware, because the request never got
 * far enough to be refused. Those are reported as *untested* rather than as
 * safe, because a check that could not run is not a check that passed.
 *
 * The route list is read from the running application, so a route added without
 * a test is still probed.
 *
 * It creates its own accounts and its own courses rather than reading the
 * demonstration data, so it can be run against a demonstration server without
 * changing what is on it beyond a few audit rows.
 *
 * Usage: php tools/probe-every-route-as-every-role.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

/*
 | Boot the console kernel before anything else.
 |
 | Without this the first factory call dies with "A facade root has not been
 | set", because requiring `bootstrap/app.php` builds the application but does
 | not start it. The kernel bootstrap is what registers the facades, loads the
 | configuration and opens the database connection, and the tool needs all three
 | before it can make a user.
 */
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = rtrim((string) env('AUDIT_BASE_URL', 'http://127.0.0.1:8000'), '/');

/*
 | The role lives on the profile, not on the user.
 |
 | `UserFactory` has an `instructor()` state and no `student()` one, because the
 | factory's `configure()` already makes a student. Writing `->student()` was a
 | guess at an API that does not exist, and a wrong guess at a factory fails as a
 | fatal rather than as a useful message, so the shape was read off the
 | instructor state instead.
 */
$withRole = function (UserRole $role): User {
    $user = User::factory()->create();

    $user->profile->forceFill(['role' => $role])->save();

    return $user;
};

$identities = [
    'guest' => null,
    'student' => $withRole(UserRole::Student),
    'instructor' => User::factory()->instructor()->create(),
    'administrator' => $withRole(UserRole::Administrator),
];

/*
 | A published course per account, so a course-scoped route resolves for the
 | account that owns it.
 |
 | One course shared by everybody would answer 200 for everyone and prove nothing;
 | the whole point is that the same identifier means different things to different
 | accounts.
 */
$courses = [];

foreach ($identities as $role => $user) {
    if ($user === null) {
        continue;
    }

    $courses[$role] = Course::factory()->create([
        'title' => 'Audit course for '.$role.' '.substr(bin2hex(random_bytes(3)), 0, 6),
        'slug' => 'audit-course-'.$role.'-'.substr(bin2hex(random_bytes(4)), 0, 8),
        'status' => CourseStatus::Published,
        'course_type' => CourseType::Free,
        'price_minor' => 0,
        'published_at' => now(),
    ]);
}

$routes = app('router')->getRoutes();

$readable = [];

foreach ($routes as $route) {
    $methods = $route->methods();

    if (in_array('GET', $methods, true) || in_array('HEAD', $methods, true)) {
        $readable[$route->uri()] = $route;
    }
}

/*
 | Which area of the application a path belongs to.
 |
 | Named rather than inferred, because the inference is the thing being tested: a
 | prefix check that guesses wrong reports a clean audit for the wrong reason. A
 | route with no prefix is public, and is left out of the area checks entirely.
 */
$areas = [
    'instructor' => ['instructor'],
    'administrator' => ['admin'],
];

$results = [];

foreach ($identities as $role => $user) {
    $kernel = $app->make(Kernel::class);

    if ($user !== null) {
        auth()->login($user);
    }

    $row = [];

    foreach ($readable as $uri => $route) {
        // A course the account can legitimately be given, or its own.
        $course = $courses[$role] ?? reset($courses);
        $ownCourse = $courses[$role] ?? $course;

        /*
         | A course is addressed by its slug, not its identifier.
         |
         | Substituting the identifier answers 404 for every course route, which
         | looks like the routes refusing to resolve rather than like the probe
         | asking the wrong question. And a route that 404s has not been refused
         | by its middleware, it has never reached it, so the audit is silent
         | about the very routes it was built to check.
         */
        $path = str_replace(['{course}', '{courseSlug}'], $ownCourse->slug, $uri);
        $path = preg_replace('/\{[^}]+\}/', '1', $path);

        $url = $base.'/'.ltrim($path, '/');

        try {
            $response = $kernel->handle(Request::create($url, 'GET'));
            $row[$uri] = $response->getStatusCode();
        } catch (Throwable $e) {
            $row[$uri] = 'crash: '.substr($e->getMessage(), 0, 120);
        }
    }

    $results[$role] = $row;

    if ($user !== null) {
        auth()->forgetUser();
        $app->forgetInstance('auth');
    }
}

$show = function (string $label, array $rows): void {
    if ($rows === []) {
        return;
    }

    echo '  '.$label.' ('.\count($rows)."):\n";

    foreach ($rows as $uri => $note) {
        echo '    '.$uri.(is_scalar($note) ? "  [{$note}]" : '')."\n";
    }

    echo "\n";
};

/*
 | The pages a guest is *supposed* to be given.
 |
 | The first version of this check flagged all ten of them, which reads like a
 | catastrophic authorization failure and is in fact the opposite: the sign in
 | page, the register page, the home page, the public catalog, the About page and
 | the two legal pages are the ones the whole application exists to show before
 | anybody signs in. A guest reaching those is the system working.
 |
 | They are named rather than inferred from a prefix, because a check that
 | guesses which pages are public is a check that will eventually decide the
 | administrator's page list is public too.
 */
$publicPages = [
    'login', 'forgot-password', 'reset-password/{token}', 'register', 'up',
    'courses', 'courses/{course}', 'terms', 'privacy', 'about', '/',
];

$guestGotIn = array_filter(
    $results['guest'],
    fn ($status, $uri) => $status === 200 && ! in_array($uri, $publicPages, true),
    ARRAY_FILTER_USE_BOTH
);

// Each role must never be handed another role's area.
$crossed = [];

foreach ($results as $role => $row) {
    if ($role === 'guest') {
        continue;
    }

    foreach ($row as $uri => $status) {
        if ($status !== 200) {
            continue;
        }

        foreach ($areas as $area => $prefixes) {
            if ($role === $area) {
                continue;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($uri, $prefix.'/') || $uri === $prefix) {
                    $crossed[$role.' reached the '.$area.' area'][$uri] = $status;
                }
            }
        }
    }
}

$crashes = [];

foreach ($results as $role => $row) {
    foreach ($row as $uri => $status) {
        if (is_string($status) && str_starts_with($status, 'crash')) {
            $crashes[$role][$uri] = $status;
        }
    }
}

$untested = [];

foreach ($results['guest'] as $uri => $status) {
    if (in_array($status, [404, 405], true)) {
        $untested[$uri] = $status;
    }
}

/*
 | Put the demonstration data back.
 |
 | This probe writes to the database the application is demonstrated from, because
 | that is the only database it can reach, and the first version of it left
 | thirteen courses behind. The catalog sorts by title, so "Audit course for
 | administrator" sat on the first page: a demonstration opened on a list of
 | fixtures with no cover images rather than on the five real courses.
 |
 | An audit that changes the thing it audits is not an audit, so the rows go back
 | before the tool reports. The identifiers were collected on the way in, so this
 | removes exactly what it created and nothing that was already there.
 */
$cleaned = 0;
foreach ($courses as $course) {
    $course->delete();
    $cleaned++;
}
foreach ($identities as $user) {
    if ($user !== null) {
        $user->delete();
    }
}
echo "\n  Probed ".count($readable).' readable routes as '.count($identities)." identities against {$base}\n";
echo "  Removed {$cleaned} fixture course(s) and the audit accounts afterwards.\n";

$show('A guest was handed a page, which needs an account', $guestGotIn);

foreach ($crossed as $label => $rows) {
    $show($label, $rows);
}

$show('Routes that crashed rather than answered', $crashes);
$show('Routes whose protection was never exercised, because they answered 404 or 405 first', $untested);

$tally = [];

foreach ($results as $row) {
    foreach ($row as $status) {
        $key = is_string($status) && str_starts_with($status, 'crash') ? 'crash' : (string) $status;
        $tally[$key] = ($tally[$key] ?? 0) + 1;
    }
}

echo "  Statuses across every probe:\n";

foreach ($tally as $status => $count) {
    echo "    {$status}: {$count}\n";
}

$blocking = \count($guestGotIn) + \count($crashes);

foreach ($crossed as $rows) {
    $blocking += \count($rows);
}

echo "\n  ".($blocking === 0
    ? 'No identity was handed a page outside its own role, and nothing crashed.'
    : "{$blocking} responses need looking at before this is demo ready.")."\n\n";

exit($blocking === 0 ? 0 : 1);
