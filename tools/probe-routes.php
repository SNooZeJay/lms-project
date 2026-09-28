<?php

/*
 | Walks every readable route as every role and reports what actually happened.
 |
 | This is the defect finder that does not guess. A static reading of a
 | controller cannot tell you whether a Policy is really consulted, because the
 | check may be three calls away inside an Action. Sending a real request does
 | tell you: it returns a status.
 |
 | Only GET and HEAD are sent. A write would change the development data this
 | repository is checked against, and a probe that damages what it is measuring
 | is worse than no probe. Write routes are covered by the test suite instead.
 |
 | What counts as a defect:
 |
 |   500            Always a defect. Nobody meant that.
 |   guest -> 200   A page that needs an account and serves one to nobody.
 |   wrong role 200 Reviewed by hand, because some pages are genuinely open to
 |                   several roles and only a person knows which.
 |
 | Run: php tools/probe-routes.php [--only=pattern]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$filter = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--only=')) {
        $filter = substr($argument, 7);
    }
}

$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';
$userModel = 'App'.'\\Models'.'\\User';

/*
 | A consistent world, not a bag of unrelated ids.
 |
 | Route model binding needs a parameter that exists, or the answer is a 404 and
 | the probe learns nothing. It also needs the ids to belong together: a lesson
 | that is not inside the course in the same URL is refused by the very
 | authorization this is trying to measure, and the run reports a clean sheet of
 | 404s while having tested nothing.
 |
 | So the world is built outward from one published course, following its real
 | parents and its real children. Published matters: an unpublished course
 | answers 404 to everybody, so choosing one would turn every course page into
 | a 404 and hide the very defects this is looking for.
 */
$world = [
    'course' => $db::table('courses')->where('status', 'published')->orderBy('id')->value('id'),
];

if ($world['course'] === null) {
    fwrite(STDERR, "  no published course exists, so the course pages cannot be measured\n");
    exit(1);
}

$world['user'] = $db::table('courses')->where('id', $world['course'])->value('instructor_id');
$world['module'] = $db::table('modules')->where('course_id', $world['course'])->orderBy('position')->value('id');
$world['lesson'] = $db::table('lessons')->where('module_id', $world['module'])->orderBy('position')->value('id');

// A material inside that lesson, not merely any material.
$world['learningMaterial'] = $db::table('learning_materials')
    ->where('lesson_id', $world['lesson'])->orderBy('id')->value('id');

if ($world['learningMaterial'] === null) {
    $world['learningMaterial'] = $db::table('learning_materials')->orderBy('id')->value('id');
}

$world['material'] = $world['learningMaterial'];

// An enrollment in that course, so the student pages have a student to be about.
$world['enrollment'] = $db::table('enrollments')->where('course_id', $world['course'])->orderBy('id')->value('id');

if ($world['enrollment'] !== null) {
    $world['student'] = $db::table('enrollments')->where('id', $world['enrollment'])->value('student_id');
}

// A quiz inside that course.
$world['quiz'] = $db::table('quizzes')->where('course_id', $world['course'])->orderBy('id')->value('id');

if ($world['quiz'] === null) {
    $world['quiz'] = $db::table('quizzes')->orderBy('id')->value('id');
}

$world['attempt'] = $db::table('quiz_attempts')->where('quiz_id', $world['quiz'])->orderBy('id')->value('id');

// The rest are filled independently, because nothing links them to this course.
foreach ([
    'attempt' => 'quiz_attempts',
    'certificate' => 'certificates',
    'announcement' => 'announcements',
    'conversation' => 'conversations',
    'notification' => 'notifications',
    'activityLog' => 'activity_logs',
    'payment' => 'payments',
] as $parameter => $table) {
    if (($world[$parameter] ?? null) !== null) {
        continue;
    }

    $world[$parameter] = $db::table($table)->orderBy('id')->value('id');
}

$roles = [];

foreach (['administrator', 'instructor', 'student'] as $role) {
    $roles[$role] = $db::table('profiles')->where('role', $role)->orderBy('user_id')->value('user_id');
}

/*
 | The two banner lines go to standard error, not standard output.
 |
 | They are progress information for a person reading the screen. On standard
 | output they would sit in front of the JSON another tool is trying to parse,
 | and the failure appears as "invalid JSON" with the real cause four lines up.
 */
fwrite(STDERR, '  accounts: '.json_encode($roles)."\n");
fwrite(STDERR, '  world:    '.json_encode(array_filter($world, fn ($v) => $v !== null))."\n\n");

$routes = collect($app->make('router')->getRoutes())
    ->filter(fn ($route) => in_array('GET', $route->methods(), true) || in_array('HEAD', $route->methods(), true));

$results = [];
$kernel = $app->make('Illuminate'.'\\Contracts'.'\\Http'.'\\Kernel');

/*
 | Resolves a route parameter against the real table.
 |
 | A route may bind on something other than the primary key: /courses/{course:slug}
 | looks for a slug, not an id. Handing it an id produces an honest 404 and a
 | clean report, which is worse than useless, because a route that was never
 | measured looks exactly like a route that is correctly refusing.
 |
 | So the binding field is read from the route and the value is looked up by that
 | field, falling back to the id when the route uses the default.
 */
$parameterTable = [
    'course' => 'courses',
    'module' => 'modules',
    'lesson' => 'lessons',
    'learningMaterial' => 'learning_materials',
    'material' => 'learning_materials',
    'enrollment' => 'enrollments',
    'quiz' => 'quizzes',
    'attempt' => 'quiz_attempts',
    'certificate' => 'certificates',
    'announcement' => 'announcements',
    'conversation' => 'conversations',
    'notification' => 'notifications',
    'activityLog' => 'activity_logs',
    'payment' => 'payments',
    'user' => 'users',
];

$resolve = function (string $parameter, array $bindingFields) use ($db, $parameterTable, $world): ?string {
    $table = $parameterTable[$parameter] ?? null;

    if ($table === null) {
        return null;
    }

    $fields = $bindingFields[$parameter] ?? [];

    // The framework reports a single field as a plain string, so reading it as
    // an array would take the first character of the column name and look up
    // "s". Both shapes are accepted rather than assuming one.
    if ($fields === [] || $fields === null) {
        // The default: the primary key. The world's value for this parameter is
        // already the id, and it was chosen to be part of a consistent set.
        return isset($world[$parameter]) ? (string) $world[$parameter] : null;
    }

    $field = is_array($fields) ? ($fields[0] ?? null) : $fields;

    if (! is_string($field) || $field === '') {
        return isset($world[$parameter]) ? (string) $world[$parameter] : null;
    }

    if ($world[$parameter] === null) {
        return null;
    }

    $value = $db::table($table)->where('id', $world[$parameter])->value($field);

    return $value === null ? null : (string) $value;
};

foreach ($routes as $route) {
    $name = $route->getName();

    if ($name === null) {
        continue;
    }

    if ($filter !== null && ! str_contains($name, $filter) && ! str_contains($route->uri(), $filter)) {
        continue;
    }

    $parameters = [];
    $bindingFields = method_exists($route, 'bindingFields') ? $route->bindingFields() : [];

    foreach ($route->parameterNames() as $parameterName) {
        $parameters[$parameterName] = $resolve($parameterName, $bindingFields);
    }

    /*
     | The placeholder is substituted into the address, then the route is bound.
     |
     | Passing the values to Request::create as a third argument puts them in the
     | request body, which a GET does not carry in the place the router looks.
     | The route would then have no parameters at all, route model binding would
     | find nothing, and every parameterised route would answer 404. That is
     | worse than no probe: it reports a clean sheet while having measured
     | nothing but the 404 handler.
     */
    $uri = $route->uri();

    foreach ($parameters as $parameterName => $value) {
        /*
         | A parameter with no real row gets a harmless stand-in rather than an
         | empty string. An empty value turns "reset-password/" into an address
         | the HTTP layer rejects outright, and the probe would stop on the first
         | one instead of reporting it. A stand-in produces an honest 404, which
         | is the correct answer for a value that does not exist.
         */
        $uri = str_replace(
            ['{'.$parameterName.'}', '{'.$parameterName.'?'],
            $value === null ? 'no-such-record' : (string) $value,
            $uri
        );
    }

    foreach (array_merge(['guest' => null], $roles) as $role => $userId) {
        if ($role !== 'guest' && $userId === null) {
            $results[] = ['route' => $name, 'role' => $role, 'status' => null, 'note' => 'no account for this role'];

            continue;
        }

        /*
         | Auth is set on the guard rather than by posting a form, which is what
         | the framework's own actingAs helper does. The guard refuses a null
         | user, so signing out has to be a different call from signing in.
         */
        $guard = $app['auth']->guard('web');

        if ($userId === null) {
            $guard->forgetUser();
        } else {
            $guard->setUser($userModel::query()->find($userId));
        }

        /*
         | The request is built inside the guard so that an address the HTTP layer
         | refuses outright is reported as a finding rather than ending the run.
         | The address is named in the note, because "Invalid URI" without the URI
         | cannot be acted on by anybody.
         */
        try {
            $request = $app->make('Illuminate'.'\\Http'.'\\Request')->create('/'.ltrim($uri, '/'), 'GET');
            $request->setRouteResolver(fn () => $route);
            $route->bind($request);

            /*
             | Queries are counted around the request.
             |
             | A page that costs a hundred queries is not obviously wrong at one
             | course and is unusable at five hundred, and the number grows with
             | the data rather than staying still. Counting it here costs nothing
             | extra, because the request is being made anyway, and it turns the
             | same walk into a performance map instead of only an authorization
             | one.
             */
            $db::enableQueryLog();
            $db::flushQueryLog();

            $response = $kernel->handle($request);

            $queries = count($db::getQueryLog());
            $db::disableQueryLog();

            $results[] = [
                'route' => $name,
                'role' => $role,
                'status' => $response->getStatusCode(),
                'uri' => '/'.ltrim($uri, '/'),
                'queries' => $queries,
                'note' => '',
            ];
        } catch (Throwable $thrown) {
            // An exception that escapes the handler is a defect in its own right,
            // and swallowing it would hide it. The class and message are named
            // because "it broke" is not something anybody can act on.
            $results[] = [
                'route' => $name,
                'role' => $role,
                'status' => 500,
                'note' => 'via /'.ltrim($uri, '/').'  '.$thrown::class.': '.substr($thrown->getMessage(), 0, 80),
            ];
        }
    }
}

$app['auth']->guard('web')->forgetUser();

/*
 | As JSON, for another tool to walk in a browser.
 |
 | The URLs are the ones this probe actually resolved and that actually rendered,
 | so a browser sweep visits the same set rather than a hand-kept list that
 | drifts. A list copied by hand is a list that quietly stops matching the
 | application, and then the sweep reports a clean run over pages that no longer
 | exist.
 */
if (in_array('--json', $argv, true)) {
    $output = [];

    foreach ($results as $result) {
        if ($result['status'] === 200) {
            $output[$result['role']][] = $result['uri'];
        }
    }

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

    exit(0);
}

/*
 | The report. Grouped so the eye goes to the exceptions rather than to a wall
 | of 200s, which is the shape a healthy run produces and the shape a broken one
 | is easy to miss inside.
 */
$broken = array_filter($results, fn (array $r): bool => $r['status'] === 500 || $r['status'] === null);

echo '  routes walked: '.count(array_unique(array_column($results, 'route')))."\n";
echo '  requests made: '.count($results)."\n\n";

if ($broken === []) {
    echo "  no 500s and no missing accounts\n\n";
} else {
    echo "  SERVER ERRORS\n";

    foreach ($broken as $failure) {
        echo sprintf("    %-46s %-14s %s\n", $failure['route'], $failure['role'], $failure['note'] ?: 'HTTP 500');
    }

    echo "\n";
}

$guestLeaks = array_filter($results, fn (array $r): bool => $r['role'] === 'guest' && $r['status'] === 200);

echo "  GUEST REACHING A PAGE AS 200\n";

if ($guestLeaks === []) {
    echo "    none\n\n";
} else {
    foreach ($guestLeaks as $leak) {
        echo '    '.$leak['route']."\n";
    }

    echo "\n";
}

$byRoute = [];
$costs = [];

foreach ($results as $result) {
    $byRoute[$result['route']][$result['role']] = $result['status'];

    // Only a page that actually rendered is worth counting. A refused request
    // costs almost nothing by design, and reporting those would make the
    // expensive column look reassuring.
    if ($result['status'] === 200) {
        $costs[$result['route']][$result['role']] = $result['queries'] ?? null;
    }
}

echo "  FULL MATRIX\n";
printf("    %-40s %5s %5s %5s %5s   %s\n", 'route', 'guest', 'admin', 'instr', 'stud', 'queries per rendered page');

foreach ($byRoute as $route => $statuses) {
    $seen = [];

    foreach (['guest', 'administrator', 'instructor', 'student'] as $role) {
        if (isset($costs[$route][$role])) {
            $seen[] = $costs[$route][$role].'q';
        }
    }

    printf(
        "    %-40s %5s %5s %5s %5s   %s\n",
        substr($route, 0, 40),
        $statuses['guest'] ?? '-',
        $statuses['administrator'] ?? '-',
        $statuses['instructor'] ?? '-',
        $statuses['student'] ?? '-',
        implode(' ', $seen) ?: '-'
    );
}

/*
 | The most expensive pages, on their own.
 |
 | A page that already costs a lot at this size is the one that will not cope at
 | ten times the size, and it is the one worth knowing about before a data
 | importer runs rather than after.
 */
$rendered = [];

foreach ($results as $result) {
    if ($result['status'] === 200 && $result['queries'] !== null) {
        $rendered[$result['route']] = max($rendered[$result['route']] ?? 0, $result['queries']);
    }
}

arsort($rendered);

echo "\n  MOST EXPENSIVE PAGES THAT RENDERED\n";

$rank = 0;

foreach ($rendered as $route => $queries) {
    printf("    %-44s %4d queries\n", $route, $queries);

    if (++$rank >= 12) {
        break;
    }
}
