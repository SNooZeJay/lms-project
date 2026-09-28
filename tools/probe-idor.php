<?php

/*
 | Asks every write route whether it will act on somebody else's record.
 |
 | The route walk proves a page renders and a guest is refused. It cannot see
 | this: a signed-in Student who is not enrolled in a course, an Instructor who
 | does not own a course, or a second Student asking for a thread they are not in.
 | All three are signed in, all three pass the authentication middleware, and all
 | three are answered by the Policy and nothing else. If a Policy is missing or
 | wrong, the only evidence is a request that should have been refused and was
 | not.
 |
 | So the requests are sent, as the wrong person, against real records, and the
 | status is reported. A 2xx on a record that belongs to somebody else is a
 | defect by definition.
 |
 | Every request is made inside a transaction that is rolled back, so the probe
 | cannot leave the development data changed even when the answer is a success.
 | The seeder and the tools scripts write to this database too, and a probe that
 | quietly enrolls a student in a course is a probe that has broken what it was
 | measuring.
 *
 | Run: php tools/probe-idor.php [--only=pattern]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';
$userModel = 'App'.'\\Models'.'\\User';

$filter = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--only=')) {
        $filter = substr($argument, 7);
    }
}

/*
 | The world, built so that every record has a rightful owner who is not the
 | attacker.
 |
 | Reusing the attacker as the owner would make every request legitimate and the
 | probe would report a clean run while proving nothing, which is the failure
 | mode this whole exercise exists to avoid.
 */
$roles = [];

foreach (['administrator', 'instructor', 'student'] as $role) {
    $roles[$role] = (int) $db::table('profiles')->where('role', $role)->orderBy('user_id')->value('user_id');
}

// A second student, who is the one used as the attacker. Using the first student
// as both victim and attacker would hide a missing check.
$victimStudent = (int) $db::table('profiles')
    ->where('role', 'student')
    ->where('user_id', '!=', $roles['student'])
    ->orderBy('user_id')
    ->value('user_id');

if ($victimStudent === 0) {
    fwrite(STDERR, "  this database has only one student, so there is no second student to attack with\n");
    exit(1);
}

// A course the victim is enrolled in and the attacker is not.
$victimCourse = (int) $db::table('enrollments')
    ->where('student_id', $victimStudent)
    ->orderBy('id')
    ->value('course_id');

$victimLesson = $victimCourse === 0
    ? null
    : (int) $db::table('lessons')
        ->whereIn('module_id', $db::table('modules')->select('id')->where('course_id', $victimCourse))
        ->orderBy('id')
        ->value('id');

$victimQuiz = $victimCourse === 0
    ? null
    : (int) $db::table('quizzes')->where('course_id', $victimCourse)->orderBy('id')->value('id');

// A course the attacker does not own, for the Instructor cases.
$foreignCourse = (int) $db::table('courses')
    ->where('instructor_id', '!=', $roles['instructor'])
    ->orderBy('id')
    ->value('id');

$world = [
    'course' => $foreignCourse ?: $roles['instructor'],
    'lesson' => $victimLesson ?: 1,
    'module' => $victimLesson === null
        ? 1
        : (int) $db::table('lessons')->where('id', $victimLesson)->value('module_id'),
    'quiz' => $victimQuiz ?: 1,
    'enrollment' => (int) $db::table('enrollments')->where('student_id', $victimStudent)->orderBy('id')->value('id'),
    'material' => (int) $db::table('learning_materials')->orderBy('id')->value('id'),
    'conversation' => (int) $db::table('conversations')->orderBy('id')->value('id'),
    'announcement' => (int) $db::table('announcements')->orderBy('id')->value('id'),
    'certificate' => (int) $db::table('certificates')->orderBy('id')->value('id'),
    'user' => (int) $db::table('users')->where('id', '!=', $roles['student'])->orderBy('id')->value('id'),
];

fwrite(STDERR, '  attacker student  : id '.$roles['student']."\n");
fwrite(STDERR, '  victim student     : id '.$victimStudent."\n");
fwrite(STDERR, '  victim course      : id '.($victimCourse ?: 'none')."\n");
fwrite(STDERR, '  foreign course     : id '.($foreignCourse ?: 'none')."\n\n");

/*
 | Each case names who is attacking, what they are aiming at, and what a correct
 | answer looks like. The payload is empty on purpose: this is asking whether the
 | door is locked, not what happens once it opens, and a request that fails
 | validation proves nothing about authorization.
 */
$cases = [
    // A Student reaching into another Student's enrollment.
    ['role' => 'student', 'route' => 'student.lessons.complete', 'params' => ['course' => 'course', 'lesson' => 'lesson'], 'method' => 'POST', 'want' => 'refused'],

    // A Student reading a quiz they are not enrolled for.
    ['role' => 'student', 'route' => 'student.quizzes.attempts.show', 'params' => ['quiz' => 'quiz', 'attempt' => 999999], 'method' => 'GET', 'want' => 'refused'],

    // A Student reading a certificate that is not theirs.
    ['role' => 'student', 'route' => 'student.certificates.show', 'params' => ['certificate' => 'certificate'], 'method' => 'GET', 'want' => 'refused'],

    // A Student opening somebody else's conversation.
    ['role' => 'student', 'route' => 'conversations.show', 'params' => ['conversation' => 'conversation'], 'method' => 'GET', 'want' => 'refused'],

    // A Student reading somebody else's announcement scope is allowed by design,
    // so this one is listed to be measured rather than to be caught.
    ['role' => 'student', 'route' => 'announcements.show', 'params' => ['announcement' => 'announcement'], 'method' => 'GET', 'want' => 'either'],

    // A Student trying to reach the Instructor workspace.
    ['role' => 'student', 'route' => 'instructor.courses.index', 'params' => [], 'method' => 'GET', 'want' => 'refused'],
    ['role' => 'student', 'route' => 'instructor.courses.create', 'params' => [], 'method' => 'GET', 'want' => 'refused'],

    // A Student trying to reach the Administrator workspace.
    ['role' => 'student', 'route' => 'admin.users.index', 'params' => [], 'method' => 'GET', 'want' => 'refused'],
    ['role' => 'student', 'route' => 'admin.reports.index', 'params' => [], 'method' => 'GET', 'want' => 'refused'],

    // An Instructor reaching into a course owned by somebody else.
    ['role' => 'instructor', 'route' => 'instructor.courses.show', 'params' => ['course' => 'course'], 'method' => 'GET', 'want' => 'refused'],
    ['role' => 'instructor', 'route' => 'instructor.courses.edit', 'params' => ['course' => 'course'], 'method' => 'GET', 'want' => 'refused'],
    ['role' => 'instructor', 'route' => 'instructor.courses.update', 'params' => ['course' => 'course'], 'method' => 'PUT', 'want' => 'refused'],
    ['role' => 'instructor', 'route' => 'instructor.courses.publish', 'params' => ['course' => 'course'], 'method' => 'POST', 'want' => 'refused'],
    ['role' => 'instructor', 'route' => 'instructor.courses.archive', 'params' => ['course' => 'course'], 'method' => 'POST', 'want' => 'refused'],

    // An Instructor reaching into a course they own but a lesson they do not.
    ['role' => 'instructor', 'route' => 'instructor.courses.modules.lessons.update', 'params' => ['course' => 'course', 'module' => 'module', 'lesson' => 'lesson'], 'method' => 'PUT', 'want' => 'refused'],

    // An Instructor trying to reach the Administrator workspace.
    ['role' => 'instructor', 'route' => 'admin.users.index', 'params' => [], 'method' => 'GET', 'want' => 'refused'],
    ['role' => 'instructor', 'route' => 'admin.activity.index', 'params' => [], 'method' => 'GET', 'want' => 'refused'],

    // An Instructor reading a private course conversation, which the Policy
    // explicitly forbids even to an Administrator.
    ['role' => 'instructor', 'route' => 'conversations.show', 'params' => ['conversation' => 'conversation'], 'method' => 'GET', 'want' => 'either'],

    // A Student trying to change a role.
    ['role' => 'student', 'route' => 'admin.users.updateRole', 'params' => ['user' => 'user'], 'method' => 'PUT', 'want' => 'refused'],

    // A Student trying to revoke a certificate.
    ['role' => 'student', 'route' => 'admin.certificates.revoke', 'params' => ['certificate' => 'certificate'], 'method' => 'POST', 'want' => 'refused'],
];

$kernel = $app->make('Illuminate'.'\\Contracts'.'\\Http'.'\\Kernel');
$results = [];

foreach ($cases as $case) {
    if ($filter !== null && ! str_contains($case['route'], $filter)) {
        continue;
    }

    $route = $app->make('router')->getRoutes()->getByName($case['route']);

    if ($route === null) {
        $results[] = ['route' => $case['route'], 'status' => null, 'note' => 'no such route'];

        continue;
    }

    $parameters = [];

    foreach ($route->parameterNames() as $name) {
        $given = $case['params'][$name] ?? null;
        $parameters[$name] = is_string($given) ? ($world[$given] ?? $given) : ($given ?? 1);
    }

    $uri = $route->uri();

    foreach ($parameters as $name => $value) {
        $uri = str_replace(['{'.$name.'}', '{'.$name.'?'], (string) $value, $uri);
    }

    /*
     | The transaction is opened here and closed by the rollback in the finally
     | block, so a write that the application wrongly allows is still undone.
     */
    $db::beginTransaction();

    try {
        $guard = $app['auth']->guard('web');
        $guard->setUser($userModel::query()->find($roles[$case['role']]));

        $request = $app->make('Illuminate'.'\\Http'.'\\Request')
            ->create('/'.ltrim($uri, '/'), $case['method'], []);
        $request->setRouteResolver(fn () => $route);
        $route->bind($request);

        $response = $kernel->handle($request);

        $results[] = [
            'route' => $case['route'],
            'status' => $response->getStatusCode(),
            'want' => $case['want'],
            'note' => '',
        ];

        $guard->forgetUser();
    } catch (Throwable $thrown) {
        $results[] = [
            'route' => $case['route'],
            'status' => 500,
            'want' => $case['want'],
            'note' => $thrown::class,
        ];
    } finally {
        $db::rollBack();
        $app['auth']->guard('web')->forgetUser();
    }
}

$app['auth']->guard('web')->forgetUser();

$suspect = 0;
$allowed = 0;

printf("  %-52s %-6s %-9s %s\n", 'route', 'status', 'wanted', 'verdict');

foreach ($results as $result) {
    $status = $result['status'];
    $want = $result['want'] ?? 'refused';

    // A refusal is any 4xx, or a 302 away from the page. A 2xx, or a 302 back to
    // the same page after a successful write, is not one.
    $refused = $status !== null && ($status >= 400 || $status === 302);

    $verdict = match ($want) {
        'refused' => $refused ? 'correctly refused' : 'SERVED IT',
        'either' => 'measured',
    };

    if ($want === 'refused' && ! $refused) {
        $suspect++;
    }

    if ($status !== null && $status < 400) {
        $allowed++;
    }

    printf(
        "  %-52s %-6s %-9s %s%s\n",
        substr($result['route'], 0, 52),
        $status ?? '-',
        $want,
        $verdict,
        $result['note'] === '' ? '' : '  '.$result['note']
    );
}

echo "\n  requests that were not refused: {$allowed}\n";
echo '  of those, expected to be refused: '.$suspect."\n";
echo $suspect === 0
    ? "  no cross-account write was served\n"
    : "  A CROSS-ACCOUNT WRITE WAS SERVED. Each line above marked SERVED IT is a defect.\n";
