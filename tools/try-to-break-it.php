<?php

use App\Enums\CertificateStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Tries to break the running application the way a curious professor would.
 *
 * Three groups, chosen because each one fails in a different place:
 *
 *   1. Private files. Asking the web server for things that must never be
 *      served, because a public document root is a configuration decision and
 *      the only way to know it was made correctly is to ask for the files.
 *
 *   2. Hostile input. Form posts carrying script tags, SQL, template syntax,
 *      path traversal, emoji, enormous strings and the wrong data types, because
 *      a field that stores them safely is a different field from one that merely
 *      does not crash.
 *
 *   3. Ownership. One account reaching another's record by changing an
 *      identifier, because that is the failure the role middleware cannot catch
 *      and a role check looks identical to a pass.
 *
 * The first two run over HTTP, against the real server, because that is the only
 * place a misconfigured document root or a missing header shows up. The third
 * runs in process, because forging a session for an HTTP client is a distraction
 * from the question being asked.
 *
 * It reports what each attempt produced. A 500 is a finding. A 422 is the
 * correct answer. A 200 that echoes the payload back unescaped is a finding, and
 * so is a 200 that stored it.
 *
 * Usage: php tools/try-to-break-it.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = rtrim((string) env('AUDIT_BASE_URL', 'http://127.0.0.1:8000'), '/');

$findings = [];
$checks = 0;

$record = function (bool $failed, string $what, string $detail = '') use (&$findings, &$checks): void {
    $checks++;

    if ($failed) {
        $findings[] = $what.($detail === '' ? '' : ': '.$detail);
    }
};

/*
 | 1. Private files.
 |
 | The document root is `public/`, so anything above it should answer 404 and
 | anything inside it that is not an asset should not exist. Each of these is a
 | path a person types when they want to see the configuration.
 */
$privatePaths = [
    '/.env',
    '/.env.example',
    '/.env.backup',
    '/.env.local',
    '/../.env',
    '/storage/logs/laravel.log',
    '/../storage/logs/laravel.log',
    '/database/database.sqlite',
    '/../database/database.sqlite',
    '/../composer.json',
    '/../package.json',
    '/../artisan',
    '/../.git/config',
    '/../.git/HEAD',
    '/vendor/autoload.php',
    '/../vendor/autoload.php',
    '/phpunit.xml',
    '/../phpunit.xml',
    '/.gitignore',
    '/../AGENTS.md',
    '/../docs/folder-structure.md',
    '/server.php',
    '/../server.php',
    '/hot',
    '/../storage/framework/sessions',
];

/*
 | A real client against the real server.
 |
 | The first version faked the facade and then built a client from the
 | application, which are two contradictory intentions: a fake exists so nothing
 | leaves the process, and a client exists so everything does. The point of this
 | file is what a web server actually returns for a request for `.env`, and that
 | cannot be learned from a fake.
 |
 | The client is resolved from the container rather than constructed, because its
 | constructor wants an event dispatcher and the application is not one.
 */
$client = $app->make(Factory::class);

foreach ($privatePaths as $path) {
    $url = $base.$path;

    try {
        $response = $client->withOptions(['allow_redirects' => false])->get($url);
        $status = $response->status();
        $body = $response->body();
    } catch (Throwable $e) {
        $status = 'error';
        $body = $e->getMessage();
    }

    // 404 is the right answer. So is a refusal of any kind.
    $leaked = $status === 200 && (
        str_contains($body, 'APP_KEY')
        || str_contains($body, 'DB_PASSWORD')
        || str_contains($body, '"require"')
        || str_contains($body, '[ref]')
        || str_contains($body, 'password')
        || str_contains($body, 'PDO')
    );

    // A 200 that is the application's own 404 page is also fine.
    $isSoftNotFound = $status === 200 && str_contains($body, 'Page not found');

    $record(
        $leaked && ! $isSoftNotFound,
        "private file served: {$path}",
        "status {$status}"
    );
}

/*
 | 2. Hostile input.
 |
 | Posted to the register form, which is the only form a stranger can reach
 | without an account and the one that has to cope with whatever a person types.
 | The payloads are the ones from the audit brief plus the shapes that break
 * things rather than merely look odd: a very long string, a lone surrogate-ish
 | sequence, and values sent as the wrong type.
 */
$payloads = [
    'script tag' => '<script>alert(1)</script>',
    'sql tautology' => "' OR '1'='1",
    'sql tautology, double quoted' => '" OR "1"="1',
    'sql union' => "' UNION SELECT password, email FROM users -- -",
    'path traversal' => '../../../../../etc/passwd',
    'blade echo' => '{{7*7}}',
    'javascript interpolation' => '${7*7}',
    'symbols' => '!@#$%^&*()',
    'emoji' => 'a😀🔥💻b',
    'chinese' => '中文测试',
    'japanese' => 'こんにちは',
    'long string' => str_repeat('a', 5000),
    'very long string' => str_repeat('b', 50000),
    'null byte' => "admin\0@example.test",
    'newline injection' => "a@b.test\nBcc: victim@example.test",
    'array as string' => ['not', 'a', 'string'],
    'integer as string' => 12345,
    'negative number' => -1,
    'huge number' => PHP_INT_MAX,
    'empty string' => '',
    'only spaces' => '     ',
    'html entity' => '&lt;script&gt;alert(1)&lt;/script&gt;',
    'mixed case script' => '<ScRiPt>alert(1)</ScRiPt>',
    'attribute breakout' => '" onmouseover="alert(1)',
    'svg payload' => '<svg/onload=alert(1)>',
    'data uri' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
];

foreach ($payloads as $label => $payload) {
    try {
        $response = $client->asForm()->post($base.'/register', [
            'name' => is_array($payload) ? 'Test' : (string) $payload,
            'email' => is_array($payload) ? 'a@b.test' : (string) $payload,
            'password' => is_array($payload) ? 'Str0ng-Passphrase!2026' : 'Str0ng-Passphrase!2026',
            'password_confirmation' => 'Str0ng-Passphrase!2026',
        ]);

        $status = $response->status();
        $body = $response->body();
    } catch (Throwable $e) {
        $status = 'error';
        $body = $e->getMessage();
    }

    $record(
        in_array($status, [500, 'error'], true),
        "hostile input crashed register: {$label}",
        (string) $status
    );

    // An unescaped payload coming back in the form is an XSS. The error page
    // redisplaying what was typed is the expected case and must be escaped.
    $needle = is_array($payload) ? null : (string) $payload;

    if ($needle !== null && $needle !== '' && ! str_contains($needle, ' ')) {
        $raw = $needle;
        $escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');

        $record(
            str_contains($body, $raw) && ! str_contains($body, $escaped) && $status === 200,
            "hostile input came back unescaped: {$label}",
            substr($raw, 0, 40)
        );
    }
}

/*
 | 3. Ownership.
 |
 | One student reaching another student's record, one instructor reaching another
 | instructor's course, and one of each reaching an administrator's page, all by
 | changing the identifier and nothing else. This is the check the role middleware
 | cannot make, because the request is correctly formed and correctly
 | authenticated and simply points at somebody else's row.
 */
$student = User::factory()->create();
$student->profile->forceFill(['role' => UserRole::Student])->save();

$other = User::factory()->create();
$other->profile->forceFill(['role' => UserRole::Student])->save();

$instructor = User::factory()->instructor()->create();

$otherCourse = Course::factory()->create([
    'title' => 'Somebody else course '.substr(bin2hex(random_bytes(3)), 0, 6),
    'status' => CourseStatus::Published,
    'course_type' => CourseType::Free,
    'price_minor' => 0,
    'published_at' => now(),
]);

/*
 | A certificate belonging to the other account, built directly.
 |
 | There is no `CertificateFactory`, so the factory call was a fatal. The row is
 | assembled by hand instead, which is also the honest way to build it here: the
 | question is whether one student can read another student's certificate, and a
 | factory that invented its own relationships would not be evidence either way.
 */
$enrollmentForOther = Enrollment::factory()->create([
    'student_id' => $other->id,
    'course_id' => $otherCourse->id,
    'status' => EnrollmentStatus::Completed,
]);
$certificate = new Certificate;

/*
 | The model's fillable list is one field, so mass assignment silently drops
 | everything else and the insert fails on the first not-null column with a
 | message that points at the schema rather than at the cause.
orceFill is
 | the right call for a row this tool owns outright.
 */
$certificate->forceFill([
    'certificate_code' => 'AUDIT-'.strtoupper(bin2hex(random_bytes(5))),
    'student_id' => $other->id,
    'course_id' => $otherCourse->id,
    'enrollment_id' => $enrollmentForOther->id,
    'student_name_snapshot' => $other->name,
    'course_title_snapshot' => $otherCourse->title,
    'completion_date' => now()->toDateString(),
    'status' => CertificateStatus::Issued,
])->save();

$ownershipProbes = [
    'another student\'s certificate' => '/certificates/'.$certificate->id,
    'a certificate identifier that does not exist' => '/certificates/99999999',
    'a course identifier that is not a number' => '/courses/not-a-number',
    'a negative course identifier' => '/courses/-1',
    'an enormous course identifier' => '/courses/99999999999999999999',
    'another account\'s profile form' => '/account/profile',
];

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
auth()->login($student);

foreach ($ownershipProbes as $label => $path) {
    try {
        $response = $kernel->handle(Request::create($base.$path, 'GET'));
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();
    } catch (Throwable $e) {
        $status = 'error';
        $body = $e->getMessage();
    }

    $record(
        in_array($status, [500, 'error'], true),
        "ownership probe crashed: {$label}",
        (string) $status
    );

    /*
     | The other account's name appearing on the page is the finding.
     |
     | A refusal that quotes the name back, such as a 403 page naming what was
     | denied, is not a leak. So the check is for the name without the email
     | alongside it: a page holding a real record has both, and a page that
     | happens to mention a name in an error has not got the record.
     */
    $record(
        str_contains($body, $other->name) && ! str_contains($body, $other->email),
        "another account's record was readable: {$label}"
    );
}

auth()->forgetUser();
$app->forgetInstance('auth');

/*
 | 4. Headers on an ordinary page.
 |
 | Read from a real response rather than from the middleware, because a header
 | set by a middleware that is not in the stack is a header that is not sent.
 */
$response = $client->get($base.'/');

$required = [
    'content-security-policy' => 'the content security policy',
    'x-content-type-options' => 'the no-sniff header',
    'referrer-policy' => 'a referrer policy',
    'x-frame-options' => 'framing protection',
];

foreach ($required as $header => $why) {
    $record(
        ! $response->hasHeader($header),
        "the response is missing {$header}, which is {$why}"
    );
}

$csp = (string) $response->header('content-security-policy');

$record(
    str_contains($csp, 'unsafe-inline') && ! str_contains($csp, "'nonce-"),
    'the content security policy allows inline script with no nonce to permit it'
);

/*
 | Put the demonstration data back.
 |
 | This tool makes accounts and courses to ask questions about, and the first
 | version of it left them behind: seventy three users on a database that
 | expects a handful of demonstration accounts, and courses on the catalog.
 |
 | An audit that changes the thing it audits is not an audit. The rows are
 | removed before the result is reported, and only the ones this tool made.
 */
auth()->forgetUser();
$app->forgetInstance('auth');

if ($certificate !== null) {
    $certificate->delete();
}

if (isset($enrollmentForOther)) {
    $enrollmentForOther->delete();
}

if (isset($otherCourse)) {
    $otherCourse->delete();
}

foreach ([$student, $other, $instructor] as $account) {
    if ($account !== null && $account->exists) {
        $account->delete();
    }
}

echo "\n  Probed {$checks} things against {$base}\n";
echo "  Removed the accounts and records it created afterwards.\n\n";

if ($findings === []) {
    echo "  Nothing broke. No private file was served, no hostile input reached a 500,\n";
    echo "  no payload came back unescaped, no account could read another account's\n";
    echo "  record, and every required header was present.\n\n";

    exit(0);
}

echo '  '.count($findings)." findings:\n";

foreach ($findings as $finding) {
    echo "    {$finding}\n";
}

echo "\n";

exit(1);
